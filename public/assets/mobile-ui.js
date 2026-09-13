document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('table').forEach(table=>{
    if(table.closest('.table-scroll')||table.dataset.mobile==='off')return;
    const headers=[...table.querySelectorAll('thead th')].map((th,index)=>th.textContent.trim()||(`Column ${index+1}`));
    if(headers.length){table.classList.add('mobile-card-table');table.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((cell,index)=>{if(cell.tagName==='TD'&&!cell.hasAttribute('data-label'))cell.dataset.label=headers[index]||'Details'}));}
    const wrapper=document.createElement('div');wrapper.className='table-scroll';wrapper.tabIndex=0;wrapper.setAttribute('role','region');wrapper.setAttribute('aria-label','Scrollable data table');table.before(wrapper);wrapper.append(table);
  });
  document.querySelectorAll('.nav-submenu').forEach(menu=>menu.addEventListener('toggle',()=>{if(!menu.open)return;document.querySelectorAll('.nav-submenu[open]').forEach(other=>{if(other!==menu)other.open=false})}));
});
