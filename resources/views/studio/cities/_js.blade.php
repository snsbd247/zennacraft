@push('studio-scripts')
<script>
    (function(){
        var csrf=document.querySelector('meta[name="csrf-token"]').content;
        var toast=document.getElementById('zc-toast');
        function showToast(m,e){ toast.textContent=m; toast.classList.toggle('err',!!e); toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2600); }
        function ajax(u,m){ return fetch(u,{method:m,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,d:d};});}); }
        document.addEventListener('click', function(e){
            var tog=e.target.closest('[data-toggle]');
            if(tog){ ajax(tog.getAttribute('data-toggle'),'POST').then(function(res){ if(res.ok){ var p=tog.closest('tr').querySelector('.zc-st'); var on=res.d.status==='active'; p.textContent=on?'Active':'Inactive'; p.className='zc-sm-pill zc-st '+(on?'zc-sm-pill--on':'zc-sm-pill--off'); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
            var del=e.target.closest('[data-delete]');
            if(del){ if(!confirm('Delete this item?')) return; ajax(del.getAttribute('data-delete'),'DELETE').then(function(res){ if(res.ok){ del.closest('tr').remove(); showToast(res.d.message);} else showToast(res.d.message||'Failed',true); }); return; }
        });
    })();
</script>
@endpush
