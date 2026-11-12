import { createClient } from 'npm:@supabase/supabase-js@2.81.1';
const db=createClient(Deno.env.get('SUPABASE_URL')!,Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,{auth:{persistSession:false,autoRefreshToken:false}});
const headers={'Access-Control-Allow-Origin':'*','Access-Control-Allow-Headers':'authorization, apikey, content-type, x-client-info','Access-Control-Allow-Methods':'POST, OPTIONS','Content-Type':'application/json','Cache-Control':'no-store'};
const reply=(v:unknown,status=200)=>new Response(JSON.stringify(v),{status,headers});
class BadRequest extends Error {}
function text(v:unknown,max=150,required=true){const s=typeof v==='string'?v.trim():'';if((required&&!s)||s.length>max)throw new BadRequest('Please check the required fields.');return s;}
function number(v:unknown,nullable=false):number|null{if(nullable&&(v===''||v==null))return null;if(v===''||v==null||typeof v==='boolean')throw new BadRequest('Enter a valid quantity.');const n=Number(v);if(!Number.isFinite(n)||n<0||n>99999999.99||Math.abs(n*100-Math.round(n*100))>0.00001)throw new BadRequest('Use a positive quantity with up to two decimal places.');return n;}
function id(v:unknown){const n=Number(v);if(!Number.isSafeInteger(n)||n<=0)throw new BadRequest('Invalid item.');return n;}
async function checked(q:any){const{data,error}=await q;if(error){console.error(error.code,error.message);throw new BadRequest(error.code==='23503'?'This category is still in use. Archive it instead.':error.message);}return data;}
Deno.serve(async req=>{
 if(req.method==='OPTIONS')return new Response(null,{headers});
 if(req.method!=='POST')return reply({error:'Method not allowed'},405);
 try{
 const{action,payload:p={}}=await req.json();
 if(action==='unlock'){
  if(!/^\d{4}$/.test(p.pin))return reply({error:'Enter your four-digit passcode.'},400);
  const ip=(req.headers.get('x-forwarded-for')||req.headers.get('x-real-ip')||'unknown').split(',').pop()!.trim();
  const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(ip));
  const key=Array.from(new Uint8Array(digest)).map(x=>x.toString(16).padStart(2,'0')).join('');
  const email=await checked(db.rpc('pin_access',{p_key:key,p_pin:p.pin}));
  if(email==='RATE_LIMIT')return reply({error:'Too many attempts. Please wait 15 minutes before trying again.'},429);
  if(!email)return reply({error:'That passcode isn’t correct. Please try again.'},401);
  const{data:link,error:linkError}=await db.auth.admin.generateLink({type:'magiclink',email});
  if(linkError)throw linkError;
  // Use a separate Auth client: verifying must never replace the service-role client's session.
  const auth=createClient(Deno.env.get('SUPABASE_URL')!,Deno.env.get('SUPABASE_ANON_KEY')!,{auth:{persistSession:false,autoRefreshToken:false}});
  const{data,error}=await auth.auth.verifyOtp({token_hash:link.properties.hashed_token,type:'magiclink'});
  if(error)throw error;
  return reply({session:data.session});
 }
 const token=req.headers.get('Authorization')?.replace(/^Bearer /i,'');
 if(!token)return reply({error:'Please unlock the workspace.'},401);
 const{data:{user},error}=await db.auth.getUser(token);
 if(error||!user)return reply({error:'Your session has expired. Please unlock again.'},401);
 const role=user.app_metadata?.cafe_role;
 if(user.app_metadata?.cafe_id!=='good-cafe'||!['admin','staff'].includes(role))return reply({error:'This account cannot access Good Cafe.'},403);
 const admin=()=>{if(role!=='admin')throw new Error('FORBIDDEN');};
 if(action==='load'){
  const r=await Promise.all([checked(db.from('items').select('*').order('name')),checked(db.from('categories').select('*').order('name')),checked(db.from('extra_buy_items').select('*').eq('is_bought',false).order('created_at',{ascending:false})),role==='admin'?checked(db.from('stock_logs').select('*').order('logged_at',{ascending:false}).limit(500)):[],role==='admin'?checked(db.from('purchase_log').select('*').order('logged_at',{ascending:false}).limit(500)):[]]);
  return reply({items:r[0],categories:r[1],extras:r[2],stockLogs:r[3],purchases:r[4],profile:{name:user.user_metadata?.full_name||role,role}});
 }
 if(action==='save_item'){
  admin();const category_id=id(p.category_id);const category=await checked(db.from('categories').select('id').eq('id',category_id).eq('is_archived',false).maybeSingle());if(!category)throw new BadRequest('Choose an active category.');
  const value={name:text(p.name),category_id,unit:text(p.unit,30),reorder_level:number(p.reorder_level),buy_location:text(p.buy_location,200,false),supplier_contact:text(p.supplier_contact,150,false),photo_path:text(p.photo_path,500,false),updated_at:new Date().toISOString()};
  if(value.photo_path&&!/^https:\/\//i.test(value.photo_path))throw new BadRequest('Photo URL must start with https://.');
  if(p.id)await checked(db.from('items').update(value).eq('id',id(p.id)).select().single());else await checked(db.from('items').insert({...value,quantity:0}).select().single());
 }else if(action==='stock'){
  const amount=number(p.amount)!;if(amount<=0||!['add','subtract'].includes(p.direction))throw new BadRequest('Enter an amount greater than zero.');
  await checked(db.rpc('adjust_stock',{p_item_id:id(p.id),p_change:p.direction==='subtract'?-amount:amount,p_staff:text(p.staff_name,100),p_actor:user.id}));
 }else if(action==='archive_item'){
  admin();if(p.archived!==true){const item=await checked(db.from('items').select('category_id').eq('id',id(p.id)).single());const category=await checked(db.from('categories').select('is_archived').eq('id',item.category_id).single());if(category.is_archived)throw new BadRequest('Restore this item’s category first.');}await checked(db.from('items').update({is_archived:p.archived===true,updated_at:new Date().toISOString()}).eq('id',id(p.id)).select().single());
 }else if(action==='save_category'){
  admin();const parent_id=p.parent_id?id(p.parent_id):null;
  if(parent_id){if(p.id&&id(p.id)===parent_id)throw new BadRequest('A category cannot be its own department.');const parent=await checked(db.from('categories').select('id').eq('id',parent_id).is('parent_id',null).eq('is_archived',false).maybeSingle());if(!parent)throw new BadRequest('Choose an active department.');if(p.id){const children=await checked(db.from('categories').select('id').eq('parent_id',id(p.id)).limit(1));if(children.length)throw new BadRequest('A department with subcategories cannot be nested.');}}
  const value={name:text(p.name,100),parent_id};if(p.id)await checked(db.from('categories').update(value).eq('id',id(p.id)).select().single());else await checked(db.from('categories').insert(value));
 }else if(action==='archive_category'){
  admin();const categoryId=id(p.id);if(p.archived===true){const a=await checked(db.from('items').select('id').eq('category_id',categoryId).eq('is_archived',false).limit(1));const c=await checked(db.from('categories').select('id').eq('parent_id',categoryId).eq('is_archived',false).limit(1));if(a.length||c.length)throw new BadRequest('Move or archive active items and subcategories first.');}else{const c=await checked(db.from('categories').select('parent_id').eq('id',categoryId).single());if(c.parent_id){const parent=await checked(db.from('categories').select('is_archived').eq('id',c.parent_id).single());if(parent.is_archived)throw new BadRequest('Restore the department first.');}}
  await checked(db.from('categories').update({is_archived:p.archived===true}).eq('id',categoryId).select().single());
 }else if(action==='buy'){
  if(p.extra)await checked(db.from('extra_buy_items').insert({name:text(p.name),unit:text(p.unit,30),quantity_needed:number(p.quantity_needed,true),buy_location:text(p.buy_location,200,false),supplier_contact:text(p.supplier_contact,150,false),added_by:user.user_metadata?.full_name||role}));else await checked(db.from('items').update({flagged_for_purchase:true,quantity_needed:number(p.quantity_needed,true)}).eq('id',id(p.id)).eq('is_archived',false).select().single());
 }else if(action==='buy_quantity')await checked(db.from(p.extra?'extra_buy_items':'items').update({quantity_needed:number(p.quantity_needed,true)}).eq('id',id(p.id)).select().single());
 else if(action==='unflag'){if(p.extra)await checked(db.from('extra_buy_items').delete().eq('id',id(p.id)));else await checked(db.from('items').update({flagged_for_purchase:false}).eq('id',id(p.id)));}
 else if(action==='purchase')await checked(db.rpc('record_purchase',{p_id:id(p.id),p_extra:p.extra===true,p_quantity:number(p.quantity_needed,true),p_name:text(p.staff_name,100),p_actor:user.id}));
 else throw new BadRequest('Unknown action.');
 return reply({ok:true});
 }catch(error){if(error.message==='FORBIDDEN')return reply({error:'Only administrators can do that.'},403);if(error instanceof BadRequest||error instanceof SyntaxError)return reply({error:error.message},400);console.error(error);return reply({error:'Something went wrong. Please try again.'},500);}
});
