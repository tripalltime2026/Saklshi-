import {db,json,failure} from '@/lib/server';
export async function GET(){try{const [tables,menu]=await Promise.all([db().prepare('SELECT * FROM dining_tables WHERE active=1 ORDER BY id').all(),db().prepare('SELECT * FROM menu_items WHERE active=1 ORDER BY category,name').all()]);return json({tables:tables.results,menu:menu.results});}catch(e){return failure(e);}}
