import {requireChatGPTUser} from '@/app/chatgpt-auth';
import AdminPanel from './panel';
export const dynamic='force-dynamic';
async function Protected(){await requireChatGPTUser('/admin');return <AdminPanel/>}
export default function Page(){return <Protected/>}
