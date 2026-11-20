(() => {
 'use strict';
 const config = window.CruiseConfig;
 if (!config) return;
 const english = config.language === 'en';
 const t = (ja, en) => english ? en : ja;
 const money = amount => english ? '¥' + Number(amount).toLocaleString('en-US') : Number(amount).toLocaleString('ja-JP') + '円';
 const uuid = () => crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g,c=>(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
 const secret = () => [...crypto.getRandomValues(new Uint8Array(32))].map(v=>v.toString(16).padStart(2,'0')).join('');
 let session;
 async function auth() { if(session && Date.now()/1000-session.issued<5400)return session; session = await request('session'); return session; }
 async function request(path, data, token) {
   const headers = { 'Content-Type': 'application/json' };
   if (session) headers['X-VC-Nonce'] = session.nonce;
   if (token) headers['X-VC-Booking-Token'] = token;
   const [route, query] = path.split('?', 2);
   const url = new URL(config.api, window.location.href);
   if (url.searchParams.has('rest_route')) {
     url.searchParams.set('rest_route', url.searchParams.get('rest_route') + route);
   } else {
     url.pathname += route;
   }
   for (const [key, value] of new URLSearchParams(query)) url.searchParams.append(key, value);
   const response = await fetch(url, { method: data ? 'POST' : 'GET', headers, body: data ? JSON.stringify(data) : undefined, credentials: 'same-origin', cache: 'no-store' });
   const result = await response.json().catch(()=>({message:t("サーバーから応答を確認できませんでした。予約状況をご確認ください。", "The server did not return a valid response. Please check your booking status.")}));
   if (!response.ok) { const error = new Error(result.message || t("処理を完了できませんでした。", "We could not complete your request.")); error.code = result.code; throw error; }
   return result;
 }
 let sdkPromise;
 function loadSDK() {
   if (window.Square) return Promise.resolve();
   if (!sdkPromise) sdkPromise = new Promise((resolve,reject)=>{ const script=document.createElement('script'); script.src=config.mode==='production'?'https://web.squarecdn.com/v1/square.js':'https://sandbox.web.squarecdn.com/v1/square.js'; script.onload=resolve; script.onerror=()=>{sdkPromise=null; reject(new Error(t("Square決済フォームを読み込めませんでした。通信環境をご確認ください。", "The Square payment form could not load. Please check your connection.")));}; document.head.appendChild(script); });
   return sdkPromise;
 }
 document.querySelectorAll('.vc-booking-form').forEach(form=>{
   const status = form.querySelector('.vc-form-status'), submit=form.querySelector('[type=submit]'), quote=form.querySelector('.vc-quote'), result=form.querySelector('.vc-result'), reconcile=form.querySelector('.vc-reconcile');
   const event=form.dataset.event, key='c365_booking_'+event;
   let state, card, source, price, busy=false;
   try { state=JSON.parse(sessionStorage.getItem(key)); } catch (_) {}
   if (!state) state={request_id:uuid(),token:secret()};
   function save() { try { sessionStorage.setItem(key,JSON.stringify(state)); } catch (_) {} }
   function message(text,error=false) {status.textContent=text;status.classList.toggle('vc-error',error);}
   function finish(r) {
     if (!['paid','reserved'].includes(r.status)) return false;
     form.querySelectorAll('input,select,button').forEach(el=>el.disabled=true);
     reconcile.hidden=true; submit.hidden=true; result.hidden=false;
     const test = r.payment_method === 'square' && r.square_mode === 'sandbox';
     let payment = r.payment_method === 'cash'
       ? t('当日現地決済。当日会場でお支払いください。', 'Pay at the meeting point on the day.')
       : t('事前カード決済完了', 'Advance card payment complete.');
     if (test) payment = t('Sandboxテスト決済完了（請求なし）', 'Sandbox test payment complete. No charge.');
     const heading = test
       ? t('テスト予約が確定しました。', 'Your test booking is confirmed.')
       : t('ご予約が確定しました。', 'Your booking is confirmed.');
     result.textContent = english
       ? `${heading}\nBooking number: ${r.id}\nCharter price: ${money(r.amount)}\nPayment: ${payment}\n\nPlease keep your booking number. A confirmation email has been queued.`
       : `${heading}\n予約番号：${r.id}\n貸切料金：${money(r.amount)}\nお支払い：${payment}\n\n確認メールの送信を手配しました。予約番号をお控えください。`;
     message(''); state.status=r.status;save();return true;
   }
   let available = false;
   submit.disabled = true;
   async function showPrice() {
     try {
       const q = await request('quote?event=' + encodeURIComponent(event));
       price = q.amount;
       available = q.available;
       quote.textContent = english ? `${money(price)} per charter (tax included)` : `貸切1組 ${money(price)}（税込）`;
       if (!available) message(q.full ? t("選択した出航枠は満席です。別の時間をお選びください。", "This departure is sold out. Please choose another time.") : t("現在、この出航枠の新規予約は受け付けていません。", "This departure is currently closed to new bookings."), true);
     } catch (e) {
       available = false;
       message(e.message, true);
     }
     submit.disabled = !available;
   }
   async function prepareCard() {
     if(card)return;
     await loadSDK(); if(!window.Square)throw new Error(t("Square SDKを読み込めませんでした。", "Square could not load."));
     const payments=window.Square.payments(config.appId,config.locationId);card=await payments.card();await card.attach(form.querySelector('.vc-square-card'));
   }
   form.querySelectorAll('[name=method]').forEach(input=>input.addEventListener('change',async()=>{
     const online=form.elements.method.value==='square';form.querySelector('.vc-card-section').hidden=!online;submit.textContent=online?t("内容を確認してカードで支払う →", "Confirm and pay by card →"):t("内容を確認して予約する →", "Confirm and book →");
     if(online){try{await prepareCard();message('');}catch(e){message(e.message,true);}}
   }));
   reconcile.addEventListener('click',async()=>{if(busy||!state.id)return;busy=true;reconcile.disabled=true;try{await auth();const r=await request('reservations/'+state.id+'/reconcile',{},state.token);if(!finish(r))message(t('現在の状態：' + r.status + '。決済確認に時間がかかる場合は運営へご連絡ください。', 'Current status: ' + r.status + '. Contact us if payment is taking longer than expected.'));}catch(e){message(e.message,true);}finally{busy=false;reconcile.disabled=false;}});
   (async()=>{
     try{await auth();if(!state.id){await showPrice();return;}if(state.id){const r=await request('reservations/'+state.id,undefined,state.token);if(!finish(r)){if(['expired','payment_failed','cancelled','refunded'].includes(r.status)){state={request_id:uuid(),token:secret()};save();message(t("前回の未完了予約は終了しています。改めて内容をご確認ください。", "Your previous unfinished booking has expired. Please review your details before trying again."));await showPrice();}else{reconcile.hidden=false;message(t("未完了の事前決済があります。新規予約を繰り返さず、決済状況をご確認ください。", "You have an unfinished payment. Check its status before starting another booking."));submit.disabled=true;form.querySelectorAll('input,select').forEach(el=>el.disabled=true);}}}}catch(e){message(e.message,true);}
   })();
   form.addEventListener('submit',async e=>{
     e.preventDefault();if(busy||!form.reportValidity())return;busy=true;submit.disabled=true;message(t("予約内容を確認しています…", "Checking your booking…"));
     try{
       await auth();const d=Object.fromEntries(new FormData(form));
       if(d.method==='square'||state.method==='square')await prepareCard();
       if(!state.id){
         const r=await request('reservations',{...d,...session,event:Number(event),passenger_count:Number(d.passenger_count),age_confirmed:form.elements.age_confirmed.checked,consent:form.elements.consent.checked,request_id:state.request_id,token:state.token,expected_amount:price,square_mode:config.mode,square_app_id:config.appId,square_location_id:config.locationId});
         state.id=r.id;state.method=r.payment_method;state.amount=r.amount;save();form.querySelectorAll('input:not([type=radio]):not([type=checkbox])').forEach(el=>el.readOnly=true);form.querySelectorAll('select,[name=method]').forEach(el=>el.disabled=true);
         if(finish(r))return;
       }
       if(state.method==='square'){
         message(t("Squareでカード情報を確認しています…", "Checking card details with Square…"));
         if(!source){const tokenized=await card.tokenize({amount:String(state.amount),currencyCode:'JPY',intent:'CHARGE',customerInitiated:true,sellerKeyedIn:false,billingContact:{email:d.email,countryCode:'JP'}});if(tokenized.status!=='OK')throw new Error(t("カード情報をご確認ください。決済はまだ完了していません。", "Please check your card details. Payment is incomplete."));source=tokenized.token;}
         message(t("決済処理中です。この画面を閉じずにお待ちください。", "Processing payment. Keep this page open."));
         const r=await request('reservations/'+state.id+'/pay',{source},state.token);
         if(!finish(r)){reconcile.hidden=false;message(t("Squareでの処理状況を確認しています。状況の再確認をご利用ください。", "Square is still processing your payment. Use Check payment status to refresh."));}
       }
     }catch(e){
       message(e.message,true);
       if(e.code==='price_changed'){await showPrice();message(t("料金が更新されました。表示料金を確認してから再度お申し込みください。", "The price changed. Review the updated price before booking again."),true);}
       if(e.code==='full') await showPrice();
       if(state.id){reconcile.hidden=false;if(e.code&&e.code.startsWith('square_')&&e.code!=='square_unknown'){message(e.message+t(" 決済は確定していません。状況を再確認するか、ページを再読み込みしてください。", " Payment has not been confirmed. Check its status or reload the page."),true);}}
     }finally{busy=false;if(!['paid','reserved'].includes(state.status))submit.disabled=!available && !state.id;}
   });
 });
 document.querySelectorAll('.vc-contact-form').forEach(form=>{
   const status=form.querySelector('.vc-form-status'),button=form.querySelector('[type=submit]');let requestId=uuid();auth().catch(()=>{});
   form.addEventListener('submit',async e=>{e.preventDefault();if(!form.reportValidity())return;button.disabled=true;status.textContent=t("送信しています…", "Sending…");status.classList.remove('vc-error');try{await auth();const data=Object.fromEntries(new FormData(form));const r=await request('contact',{...data,...session,request_id:requestId,consent:form.elements.consent.checked});status.textContent=r.message;form.reset();requestId=uuid();}catch(e){status.textContent=e.message;status.classList.add('vc-error');}finally{button.disabled=false;}});
 });
})();
