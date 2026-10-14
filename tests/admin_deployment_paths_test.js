const vm=require('node:vm'),fs=require('node:fs'),assert=require('node:assert/strict');
for(const base of ['', '/webgoc2026', '/nested/site']) {
 const context={document:{querySelector:()=>({content:base}),addEventListener(){}},console};
 vm.createContext(context);
 vm.runInContext(fs.readFileSync(__dirname+'/../admin/assets/js/admin.js','utf8'),context);
 assert.equal(context.adminUrl('/admin/api/live_sync.php'),base+'/admin/api/live_sync.php');
 assert.equal(context.adminUrl('/favicon-32x32.png'),base+'/favicon-32x32.png');
}
console.log('Admin browser deployment paths OK: root, subfolder, nested');
