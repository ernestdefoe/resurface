import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import BumpLog from '../../common/BumpLog';

export default class LogModal extends Modal {
  className() {
    return 'BuoyLogModal Modal--medium';
  }

  title() {
    return app.translator.trans('ernestdefoe-buoy.forum.log.title');
  }

  content() {
    return (
      <div className="Modal-body">
        <BumpLog discussion={this.attrs.discussion} />
      </div>
    );
  }
}
