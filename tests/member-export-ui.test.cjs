const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('wordpress/aac-member-portal/includes/class-aac-member-portal-member-export.php','utf8');
const start=source.indexOf('<script>')+8,end=source.indexOf('</script>',start);
const script=source.slice(start,end).replace(/<\?php echo wp_json_encode\(\$config\); \?>/,JSON.stringify({url:'/admin-ajax.php',nonce:'nonce',id:'job1',progress:{status:'running',total:100,processed:0,rows:0}})).replace(/<\?php echo esc_js\(self::AJAX\); \?>/g,'aac_member_export_step');
function fixture(results){
 const elements={};for(const key of ['status','meter','download','resume'])elements[key]={hidden:true,addEventListener(name,fn){this[name]=fn;}};
 const calls=[];
 const context={document:{getElementById(){return {querySelector(selector){return elements[selector.match(/data-export-(\w+)/)[1]];}};}},URLSearchParams,AbortController,setTimeout,clearTimeout,fetch:async(url,options)=>{calls.push(options);const next=results.shift();if(next instanceof Error)throw next;return {ok:true,json:async()=>next};}};
 vm.runInNewContext(script,context);
 return {elements,calls};
}
async function settle(check){for(let n=0;n<30;n++){if(check())return;await new Promise(r=>setTimeout(r,30));}throw new Error('UI did not settle');}
test('shows progress, completes and exposes only the finished download',async()=>{
 const {elements,calls}=fixture([{success:true,data:{status:'running',processed:50,total:100,rows:50}},{success:true,data:{status:'ready',processed:100,total:100,rows:100,download:'/admin-post.php?job=job1'}}]);
 assert.equal(elements.download.hidden,true);
 await settle(()=>elements.download.hidden===false);
 assert.equal(elements.meter.value,100);assert.match(elements.status.textContent,/Ready to download/);
 assert.equal(elements.download.href,'/admin-post.php?job=job1');
 assert.equal(calls.length,2);assert.equal(calls[0].body.get('_ajax_nonce'),'nonce');assert.equal(calls[0].body.get('job'),'job1');
});
test('network failure preserves the job and exposes Resume rather than a partial download',async()=>{
 const {elements,calls}=fixture([new Error('network')]);
 await settle(()=>elements.resume.hidden===false);
 assert.equal(elements.download.hidden,true);assert.match(elements.status.textContent,/Progress is saved/);assert.equal(calls.length,1);
});
