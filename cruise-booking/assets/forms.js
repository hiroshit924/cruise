(() => {
 'use strict';
 const config = window.CruiseConfig;
 if (!config) return;
 const uuid = () => crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g,c=>(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
 const secret = () => [...crypto.getRandomValues(new Uint8Array(32))].map(v=>v.toString(16).padStart(2,'0')).join('');
 let session;
 async function auth() { if(session && Date.now()/1000-session.issued<5400)return session; session = await request('session'); return session; }
 async function request(path, data, token) {
   const headers = { 'Content-Type': 'application/json' };
   if (session) headers['X-VC-Nonce'] = session.nonce;
   if (token) headers['X-VC-Booking-Token'] = token;
   const response = await fetch(config.api + path, { method: data ? 'POST' : 'GET', headers, body: data ? JSON.stringify(data) : undefined, credentials: 'same-origin', cache: 'no-store' });
   const result = await response.json().catch(()=>({message:'サーバーから応答を確認できませんでした。予約状況をご確認ください。'}));
   if (!response.ok) { const error = new Error(result.message || '処理を完了できませんでした。'); error.code = result.code; throw error; }
   return result;
 }
 let sdkPromise;
 function loadSDK() {
   if (window.Square) return Promise.resolve();
   if (!sdkPromise) sdkPromise = new Promise((resolve,reject)=>{ const script=document.createElement('script'); script.src=config.mode==='production'?'https://web.squarecdn.com/v1/square.js':'https://sandbox.web.squarecdn.com/v1/square.js'; script.onload=resolve; script.onerror=()=>{sdkPromise=null; reject(new Error('Square決済フォームを読み込めませんでした。通信環境をご確認ください。'));}; document.head.appendChild(script); });
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
     result.textContent='ご予約が確定しました。\n予約番号：'+r.id+'\n貸切料金：'+Number(r.amount).toLocaleString('ja-JP')+'円\nお支払い：'+(r.payment_method==='cash'?'当日現地決済。当日会場でお支払いください。':'事前カード決済完了')+'\n\n確認メールの送信を手配しました。予約番号をお控えください。';
     message(''); state.status=r.status;save();return true;
   }
   let available = false;
   submit.disabled = true;
   async function showPrice() {
     try {
       const q = await request('quote?event=' + encodeURIComponent(event));
       price = q.amount;
       available = q.available;
       quote.textContent = '貸切1組 ' + Number(price).toLocaleString('ja-JP') + '円（税込）';
       if (!available) message('現在、この出航枠の新規予約は受け付けていません。', true);
     } catch (e) {
       available = false;
       message(e.message, true);
     }
     submit.disabled = !available;
   }
   async function prepareCard() {
     if(card)return;
     await loadSDK(); if(!window.Square)throw new Error('Square SDKを読み込めませんでした。');
     const payments=window.Square.payments(config.appId,config.locationId);card=await payments.card();await card.attach(form.querySelector('.vc-square-card'));
   }
   form.querySelectorAll('[name=method]').forEach(input=>input.addEventListener('change',async()=>{
     const online=form.elements.method.value==='square';form.querySelector('.vc-card-section').hidden=!online;submit.textContent=online?'内容を確認してカードで支払う →':'内容を確認して予約する →';
     if(online){try{await prepareCard();message('');}catch(e){message(e.message,true);}}
   }));
   reconcile.addEventListener('click',async()=>{if(busy||!state.id)return;busy=true;reconcile.disabled=true;try{await auth();const r=await request('reservations/'+state.id+'/reconcile',{},state.token);if(!finish(r))message('現在の状態：'+r.status+'。決済確認に時間がかかる場合は運営へご連絡ください。');}catch(e){message(e.message,true);}finally{busy=false;reconcile.disabled=false;}});
   (async()=>{
     try{await auth();if(!state.id){await showPrice();return;}if(state.id){const r=await request('reservations/'+state.id,undefined,state.token);if(!finish(r)){if(['expired','payment_failed','cancelled','refunded'].includes(r.status)){state={request_id:uuid(),token:secret()};save();message('前回の未完了予約は終了しています。改めて内容をご確認ください。');await showPrice();}else{reconcile.hidden=false;message('未完了の事前決済があります。新規予約を繰り返さず、決済状況をご確認ください。');submit.disabled=true;form.querySelectorAll('input,select').forEach(el=>el.disabled=true);}}}}catch(e){message(e.message,true);}
   })();
   form.addEventListener('submit',async e=>{
     e.preventDefault();if(busy||!form.reportValidity())return;busy=true;submit.disabled=true;message('予約内容を確認しています…');
     try{
       await auth();const d=Object.fromEntries(new FormData(form));
       if(d.method==='square'||state.method==='square')await prepareCard();
       if(!state.id){
         const r=await request('reservations',{...d,...session,event:Number(event),passenger_count:Number(d.passenger_count),age_confirmed:form.elements.age_confirmed.checked,consent:form.elements.consent.checked,request_id:state.request_id,token:state.token,expected_amount:price,square_mode:config.mode,square_app_id:config.appId,square_location_id:config.locationId});
         state.id=r.id;state.method=r.payment_method;state.amount=r.amount;save();form.querySelectorAll('input:not([type=radio]):not([type=checkbox])').forEach(el=>el.readOnly=true);form.querySelectorAll('select,[name=method]').forEach(el=>el.disabled=true);
         if(finish(r))return;
       }
       if(state.method==='square'){
         message('Squareでカード情報を確認しています…');
         if(!source){const tokenized=await card.tokenize({amount:String(state.amount),currencyCode:'JPY',intent:'CHARGE',customerInitiated:true,sellerKeyedIn:false,billingContact:{email:d.email,countryCode:'JP'}});if(tokenized.status!=='OK')throw new Error('カード情報をご確認ください。決済はまだ完了していません。');source=tokenized.token;}
         message('決済処理中です。この画面を閉じずにお待ちください。');
         const r=await request('reservations/'+state.id+'/pay',{source},state.token);
         if(!finish(r)){reconcile.hidden=false;message('Squareでの処理状況を確認しています。状況の再確認をご利用ください。');}
       }
     }catch(e){
       message(e.message,true);
       if(e.code==='price_changed'){await showPrice();message('料金が更新されました。表示料金を確認してから再度お申し込みください。',true);}
       if(state.id){reconcile.hidden=false;if(e.code&&e.code.startsWith('square_')&&e.code!=='square_unknown'){message(e.message+' 決済は確定していません。状況を再確認するか、ページを再読み込みしてください。',true);}}
     }finally{busy=false;if(!['paid','reserved'].includes(state.status))submit.disabled=!available && !state.id;}
   });
 });
 document.querySelectorAll('.vc-contact-form').forEach(form=>{
   const status=form.querySelector('.vc-form-status'),button=form.querySelector('[type=submit]');let requestId=uuid();auth().catch(()=>{});
   form.addEventListener('submit',async e=>{e.preventDefault();if(!form.reportValidity())return;button.disabled=true;status.textContent='送信しています…';status.classList.remove('vc-error');try{await auth();const data=Object.fromEntries(new FormData(form));const r=await request('contact',{...data,...session,request_id:requestId,consent:form.elements.consent.checked});status.textContent=r.message;form.reset();requestId=uuid();}catch(e){status.textContent=e.message;status.classList.add('vc-error');}finally{button.disabled=false;}});
 });
})();
