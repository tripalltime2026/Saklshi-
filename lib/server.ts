import {env} from 'cloudflare:workers';
import {getChatGPTUser} from '@/app/chatgpt-auth';
export function db(){if(!env.DB)throw new Error('Database unavailable');return env.DB;}
export async function admin(){const user=await getChatGPTUser(); const emails=((env as unknown as Record<string,string>).ADMIN_EMAILS||'').toLowerCase().split(',').map(s=>s.trim());return !!user&&emails.includes(user.email.toLowerCase());}
export function json(data:unknown,status=200){return Response.json(data,{status,headers:{'Cache-Control':'no-store'}});}
export function sameOrigin(r:Request){return r.headers.get('origin')===new URL(r.url).origin;}
export function today(){return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Tbilisi',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());}
export function failure(e:unknown){console.error('Reservation operation failed',e instanceof Error?e.message:'unknown');return json({error:'მონაცემების ჩატვირთვა ვერ მოხერხდა. სცადეთ ხელახლა.'},503);}
