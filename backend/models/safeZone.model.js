const pool = require('../config/db');
const { HttpError } = require('../utils/respond');
const v = require('../utils/validate');
const audit = require('../utils/audit');

const clean = (b) => [
  v.str(b.zone_name, 'Zone name', 2, 100), v.str(b.address, 'Address', 2, 255),
  v.str(b.city, 'City', 2, 100), v.flag(b.is_geofenced),
];

exports.list = async () => (await pool.query('SELECT * FROM safe_meetup_zones ORDER BY id'))[0];
exports.get = async (id) => (await pool.execute('SELECT * FROM safe_meetup_zones WHERE id = ?', [id]))[0][0] || null;

exports.create = async (b, actor) => {
  const [r] = await pool.execute(
    'INSERT INTO safe_meetup_zones (zone_name, address, city, is_geofenced) VALUES (?,?,?,?)', clean(b));
  audit(actor.id, 'Created Safe Zone', `Zone #${r.insertId}`);
  return exports.get(r.insertId);
};
exports.update = async (id, b, actor) => {
  const [r] = await pool.execute(
    'UPDATE safe_meetup_zones SET zone_name=?, address=?, city=?, is_geofenced=? WHERE id=?', [...clean(b), id]);
  if (!r.affectedRows) return null;
  audit(actor.id, 'Updated Safe Zone', `Zone #${id}`);
  return exports.get(id);
};
exports.remove = async (id, actor) => {
  const [r] = await pool.execute('DELETE FROM safe_meetup_zones WHERE id = ?', [id]);   // blocked (1451 -> 409) if meetups use it
  if (r.affectedRows) audit(actor.id, 'Deleted Safe Zone', `Zone #${id}`);
  return r.affectedRows;
};

// LEFT JOIN report: EVERY zone, even zones with 0 meetups (COUNT(m.id) ignores NULL, so empty zones show 0)
exports.report = async () => (await pool.query(
  `SELECT z.id AS zone_id, z.zone_name, z.address, z.city,
          COUNT(m.id) AS total_scheduled_meetups, MAX(m.scheduled_time) AS latest_meetup
   FROM safe_meetup_zones z
   LEFT JOIN meetup_schedules m ON z.id = m.zone_id
   GROUP BY z.id, z.zone_name, z.address, z.city
   ORDER BY total_scheduled_meetups DESC, z.zone_name`))[0];
