import app from 'flarum/forum/app';

/** Bumps the discussion, then lifts it to the top of a newest-activity list. */
export default function bump(discussion) {
  return discussion.save({ buoyBump: true }).then(() => {
    if (app.discussions && app.discussions.currentSort() === '-lastPostedAt') {
      app.discussions.addDiscussion(discussion);
    }

    app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-buoy.forum.bumped_alert'));
  });
}
