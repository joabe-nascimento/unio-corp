import { Application } from '@hotwired/stimulus';
import CoreProjetosController from './controllers/core_projetos_controller.js';
import DevKanbanBoardController from './controllers/dev_kanban_board_controller.js';

const application = Application.start();
application.register('core-projetos', CoreProjetosController);
application.register('dev-kanban-board', DevKanbanBoardController);
