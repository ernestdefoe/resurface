import Extend from 'flarum/common/extenders';
import Model from 'flarum/common/Model';
import Discussion from 'flarum/common/models/Discussion';

export class BuoyBump extends Model {}
Object.assign(BuoyBump.prototype, {
  source: Model.attribute('source'),
  createdAt: Model.attribute('createdAt', Model.transformDate),
  discussion: Model.hasOne('discussion'),
  user: Model.hasOne('user'),
});

export default [
  new Extend.Store().add('buoy-bumps', BuoyBump),
  new Extend.Model(Discussion)
    .attribute('buoyBumpedAt', Model.transformDate)
    .attribute('buoyCanBump')
    .attribute('buoyNextBumpAt', Model.transformDate)
    .attribute('buoyBumpsLeft')
    .attribute('buoyCooldown')
    .attribute('buoyCanSchedule')
    .attribute('buoyCanViewLog')
    .attribute('buoySchedule'),
];
