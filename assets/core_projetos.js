import { Application } from '@hotwired/stimulus';
import CoreProjetosController from './controllers/core_projetos_controller.js';
import DevKanbanBoardController from './controllers/dev_kanban_board_controller.js';

const stimulus = Application.start();
stimulus.register('core-projetos', CoreProjetosController);
stimulus.register('dev-kanban-board', DevKanbanBoardController);
