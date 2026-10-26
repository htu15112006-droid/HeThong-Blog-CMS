(() => {
  'use strict';
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', e => { if (!window.confirm(form.dataset.confirm)) e.preventDefault(); }));
  const cfg = window.CMS_STUDIO;
  if (!cfg) return;
  async function api(path, params = {}) {
    // rest_url may use ?rest_route=... on installations without pretty permalinks.
    const url = new URL(cfg.api, location.origin);
    if (url.searchParams.has('rest_route')) url.searchParams.set('rest_route', url.searchParams.get('rest_route').replace(/\/$/, '') + '/' + path);
    else url.pathname = url.pathname.replace(/\/$/, '') + '/' + path;
    Object.entries(params).forEach(([k,v]) => url.searchParams.set(k,v));
    const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 55000);
    try {
      const res = await fetch(url, {credentials:'same-origin', headers:{'X-WP-Nonce':cfg.nonce},signal:controller.signal});
      const body = await res.json();
      if (!res.ok) throw new Error(body.message || 'Không tải được dữ liệu.');
      return body;
    } finally {clearTimeout(timeout);}
  }
  const val = (d,k) => Array.isArray(d[k]) && d[k][0]?.value ? Number(d[k][0].value[1]) : null;
  let metricsBusy = false;
  async function metrics() {
    if(metricsBusy)return;metricsBusy=true;
    const message=document.querySelector('#metrics-message');const button=document.querySelector('#refresh-metrics');button.disabled=true;message.textContent='Đang cập nhật…';
    try {
      const data = await api('metrics');
      document.querySelectorAll('[data-metric]').forEach(el=>{const k=el.dataset.metric,n=val(data,k);el.textContent=n===null||!Number.isFinite(n)?'—':(['cpu','ram'].includes(k)?n.toFixed(1)+'%':k==='requests'?n.toFixed(3):Math.round(n).toString());});
      const target=document.querySelector('#services');target.replaceChildren();
      const jobs=Array.isArray(data.services)?data.services:[];
      jobs.forEach(job=>{const row=document.createElement('div');row.className='service-row';const name=document.createElement('span');name.textContent=job.metric.job;const badge=document.createElement('span');const up=Number(job.value[1])===1;badge.className='badge'+(up?'':' off');badge.textContent=up?'Đang thu thập':'Mất kết nối';row.append(name,badge);target.append(row);});
      ['mysql','nginx'].forEach(k=>{const row=document.createElement('div');row.className='service-row';const name=document.createElement('span');name.textContent=k==='mysql'?'Kết nối MySQL':'Kết nối Nginx';const badge=document.createElement('span');const n=val(data,k);badge.className='badge'+(n===1?'':' off');badge.textContent=n===1?'Hoạt động':n===null?'Chưa có dữ liệu':'Không kết nối';row.append(name,badge);target.append(row);});
      const values=data.history?.[0]?.values?.map(x=>Number(x[1])).filter(Number.isFinite)||[];
      const svg=document.querySelector('#requests-chart');svg.replaceChildren();
      if(values.length>1){const max=Math.max(...values,0.001),points=values.map((v,i)=>`${10+i*580/(values.length-1)},${165-v/max*145}`).join(' ');const line=document.createElementNS('http://www.w3.org/2000/svg','polyline');line.setAttribute('points',points);line.setAttribute('fill','none');line.setAttribute('stroke','#176853');line.setAttribute('stroke-width','3');svg.append(line);document.querySelector('#chart-range').textContent=`Từ 0 đến ${max.toFixed(3)} yêu cầu/giây · ${values.length} mẫu trong 15 phút.`;}else{const t=document.createElementNS('http://www.w3.org/2000/svg','text');t.setAttribute('x','25');t.setAttribute('y','90');t.setAttribute('fill','#687772');t.textContent='Chưa có đủ mẫu để vẽ biểu đồ.';svg.append(t);}
      const errors=Object.values(data).filter(x=>x?.error);
      message.textContent=errors.length?'Một số metrics chưa tải được. Kiểm tra Prometheus và các exporter.':'Cập nhật lúc '+new Date(data.updated).toLocaleTimeString('vi-VN')+'. Dấu — nghĩa là chưa có dữ liệu.';
    }catch(e){message.textContent=e.name==='AbortError'?'Dịch vụ phản hồi quá lâu. Thử làm mới.':e.message;document.querySelector('#services').textContent='Chưa tải được trạng thái dịch vụ.';}finally{metricsBusy=false;button.disabled=false;}
  }
  if(document.querySelector('#monitoring')){metrics();document.querySelector('#refresh-metrics').addEventListener('click',metrics);setInterval(()=>{if(!document.hidden)metrics();},30000);}
  let mode='access',logBusy=false;
  async function logs(){if(logBusy)return;logBusy=true;const output=document.querySelector('#log-output'),message=document.querySelector('#logs-message');message.textContent='Đang truy vấn Loki…';
    try{const data=await api('logs',{mode});document.querySelector('#log-query').textContent=data.query;output.replaceChildren();
      if(mode==='count'){const series=data.data.result?.[0],values=series?.values||[];const last=values.at(-1);output.textContent=last?`${Number(last[1]).toLocaleString('vi-VN')} yêu cầu HTTP trong 5 phút\nMẫu lúc ${new Date(Number(last[0])*1000).toLocaleTimeString('vi-VN')}`:'Không có mẫu trong khoảng thời gian này. Đây không phải kết luận có 0 yêu cầu.';}
      else{const rows=(data.data.result||[]).flatMap(s=>s.values||[]).sort((a,b)=>b[0].localeCompare(a[0])).slice(0,80);if(!rows.length)output.textContent='Không có log phù hợp trong 15 phút gần nhất. Hãy truy cập website hoặc tạo yêu cầu 404 rồi làm mới.';rows.forEach(([time,line])=>{const row=document.createElement('div');row.className='logline';const stamp=document.createElement('time');stamp.textContent=new Date(Number(time)/1000000).toLocaleTimeString('vi-VN');const text=document.createElement('span');text.textContent=line;row.append(stamp,text);output.append(row);});}
      message.textContent='Dữ liệu thật từ Loki · cập nhật '+new Date().toLocaleTimeString('vi-VN');
    }catch(e){output.textContent='Không tải được log.';message.textContent=e.message;}finally{logBusy=false;}
  }
  if(document.querySelector('#log-output')){logs();document.querySelector('#refresh-logs').addEventListener('click',logs);document.querySelectorAll('.log-mode').forEach(btn=>btn.addEventListener('click',()=>{if(logBusy)return;mode=btn.dataset.mode;document.querySelectorAll('.log-mode').forEach(b=>b.classList.toggle('active',b===btn));logs();}));}
})();
