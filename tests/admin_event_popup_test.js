const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor(tag) {this.tag = tag;this.children = [];this.listeners = {};this.dataset = {};}
    append(...children) {this.children.push(...children);}
    addEventListener(name, callback) {this.listeners[name] = callback;}
    setAttribute(name, value) {this[name] = value;}
    showModal() {this.open = true;}
    close() {this.open = false;}
    remove() {this.removed = true;}
    focus() {this.focused = true;}
    click() {this.listeners.click?.({target:this});}
}
const body = new Element('body');
const context = {document:{body,createElement:tag=>new Element(tag),addEventListener(){}},console};
vm.createContext(context);
const source = fs.readFileSync(__dirname+'/../admin/assets/js/events.js','utf8');
vm.runInContext(source, context);
const latest = () => body.children.at(-1);
(async () => {
    let promise = context.eventConfirmToggle({name:'Hùng Vương <script>',applied_enabled:true,consumed_ids:[1220],kept_ids:[1776]});
    let dialog = latest();
    assert.equal(dialog.open, true);
    assert.match(dialog.children[0].children[0].textContent, /Hùng Vương <script>/);
    assert.equal(dialog.children[2].children[0].focused, true, 'destructive confirmation defaults focus to cancel');
    dialog.children[2].children[0].click();
    assert.equal(await promise, false, 'cancel cannot submit a command');
    assert.equal(dialog.removed, true);
    promise = context.eventConfirmToggle({name:'Hè',applied_enabled:false});
    dialog = latest();dialog.children[2].children[1].click();
    assert.equal(await promise, true, 'only explicit confirmation accepts');
    promise = context.eventPopup({title:'Kết quả',lines:['Đã áp dụng']});
    dialog = latest();let prevented = false;
    dialog.listeners.cancel({preventDefault(){prevented = true;}});
    assert.equal(await promise, false);assert.equal(prevented, true, 'Escape settles the promise');
    promise = context.eventPopup({title:'Đóng'});dialog = latest();dialog.children[0].children[1].click();
    assert.equal(await promise, false, 'close button settles the promise');
    assert.doesNotMatch(source, /\b(?:window\.)?(?:confirm|alert)\s*\(/, 'event page uses styled popups');
    console.log('event popup confirm/cancel/Escape/close/safe text OK');
})().catch(error=>{console.error(error);process.exitCode=1;});
