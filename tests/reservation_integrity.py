import sqlite3, pathlib, unittest

class ReservationIntegrity(unittest.TestCase):
 def setUp(self):
  self.db=sqlite3.connect(':memory:');self.db.execute('PRAGMA foreign_keys=ON')
  for p in sorted(pathlib.Path('drizzle').glob('*.sql')):self.db.executescript(p.read_text())
 def reserve(self,id,start=1200,table=1):
  with self.db:
   self.db.execute("INSERT INTO bookings (id,date,start,end,table_id,guests,first_name,last_name,phone,birth_day,birth_month,items,created_at) VALUES (?,?,?,?,?,2,'Test','Guest','555123456',29,2,'[]','2026-09-08')",(id,'2026-09-18',start,start+120,table))
   for minute in range(start,start+120,30):self.db.execute('INSERT INTO booking_slots VALUES (?,?,?,?,?)',(id+str(minute),id,table,'2026-09-18',minute))
 def test_overlapping_reservation_rolls_back(self):
  self.reserve('one')
  with self.assertRaises(sqlite3.IntegrityError):self.reserve('two',1230)
  self.assertEqual(self.db.execute('SELECT count(*) FROM bookings').fetchone()[0],1)
  self.assertEqual(self.db.execute('SELECT count(*) FROM booking_slots').fetchone()[0],4)
 def test_adjacent_reservation_and_other_table(self):
  self.reserve('one');self.reserve('two',1320);self.reserve('three',1200,2)
  self.assertEqual(self.db.execute('SELECT count(*) FROM bookings').fetchone()[0],3)
 def test_cancel_releases_table(self):
  self.reserve('one')
  with self.db:
   self.db.execute("UPDATE bookings SET status='cancelled' WHERE id='one'")
   self.db.execute("DELETE FROM booking_slots WHERE booking_id='one'")
  self.reserve('two')
  self.assertEqual(self.db.execute("SELECT status FROM bookings WHERE id='one'").fetchone()[0],'cancelled')
if __name__=='__main__':unittest.main()
