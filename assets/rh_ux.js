import { Application } from '@hotwired/stimulus';
import RhWizardController from './controllers/rh_wizard_controller.js';
import RhProcessWizardController from './controllers/rh_process_wizard_controller.js';
import TomSelectController from './controllers/tom_select_controller.js';
import HuplexChartController from './controllers/huplex_chart_controller.js';

const application = Application.start();
application.register('rh-wizard', RhWizardController);
application.register('rh-process-wizard', RhProcessWizardController);
application.register('tom-select', TomSelectController);
application.register('huplex-chart', HuplexChartController);

export default application;
