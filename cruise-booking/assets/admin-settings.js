(() => {
  'use strict';
  const mode=document.querySelector('#vc-square-mode');
  if(!mode)return;
  const panels=[...document.querySelectorAll('[data-vc-square-mode]')];
  function showMode(){
    panels.forEach(panel=>{panel.hidden=panel.dataset.vcSquareMode!==mode.value;});
    const label=document.querySelector('#vc-square-selection');
    if(label)label.textContent=(mode.value==='production'?'Production（本番）':'Sandbox（テスト専用）')+'のIDを表示しています。環境変更を適用するには「設定を保存」を押してください。';
  }
  mode.addEventListener('change',showMode);
  showMode();
})();
