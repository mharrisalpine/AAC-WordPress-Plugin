const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('wordpress/aac-member-portal/templates/checkout-preferences.php','utf8');
const script = source.slice(source.indexOf('const bindCheckoutRequiredMarkers'));
test('marks billing and enabled eligibility fields, leaves optional and hidden disabled fields unmarked', () => {
  const names = ['bfirstname','bstate','baddress2','t_shirt_sizet_shirt','aac_sms_marketing_opt_in','graduation_date','university_or_school','service'];
  const labels = {};
  const controls = names.map(name => {
    labels[name] = {star:null, querySelector(){return this.star},appendChild(star){this.star=star;star.remove=()=>{this.star=null}}};
    return {name,id:name,required:name==='graduation_date'||name==='university_or_school',disabled:name==='service',attrs:{},
      closest(){return {classList:{contains(){return name==='service'}}}},
      getAttribute(key){return this.attrs[key]},setAttribute(key,value){this.attrs[key]=value},removeAttribute(key){delete this.attrs[key]}};
  });
  const handlers = {};
  const form = {dataset:{},querySelectorAll(selector){return selector.startsWith('input')?controls:[]},querySelector(selector){return labels[selector.match(/for="([^"]+)"/)[1]]},addEventListener(name,cb){handlers[name]=cb}};
  vm.runInNewContext(script+'\nbindCheckoutRequiredMarkers();', {document:{getElementById(){return form},createElement(){return {setAttribute(){}}}},MutationObserver:class {observe(){}},requestAnimationFrame:fn=>fn()});
  for (const name of ['bfirstname','bstate','graduation_date']) assert.ok(labels[name].star, name);
  for (const name of ['baddress2','t_shirt_sizet_shirt','aac_sms_marketing_opt_in','university_or_school','service']) assert.equal(labels[name].star,null,name);
  controls.find(c=>c.name==='graduation_date').disabled=true;
  controls.find(c=>c.name==='service').disabled=false;
  handlers.change();
  assert.equal(labels.graduation_date.star,null);
  assert.ok(labels.service.star);
  assert.equal(controls.find(c=>c.name==='bstate').required,true);
});
