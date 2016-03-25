/**
 * We use cocktail to mix in these common elements into both the post and comment views
 * instead of using event delegation in order to keep the view management code (ignore edit
 * actions) and model update code simple
 */
/* global enquire, console */
var ChecklistsExtendsCommon = ( function( $ ) {
	return {
		events: {
			'click .o2-task-item-text': 'onClickTaskText',
			'click .o2-task-item :checkbox': 'onClickTaskCheckbox',
			'click .o2-add-task': 'onClickTaskAdd',
			'click .o2-edit-task': 'onClickTaskEdit',
			'click .o2-delete-task': 'onClickTaskDelete',
			'blur .o2-task-item-input': 'onBlurTaskEdit',
			'keydown .o2-task-item-input': 'onKeyPressTask'
		},

		initialize: function() {
			this.options.bp550Match = false;
			this.options.ignoreBlur = false;
			this.options.previousSaveInProgress = false;
			this.options.checklistRequestCount = 0;

			this.listenTo( this.model, 'o2-post-rendered', this.updateTaskControls );
			this.listenTo( this.model, 'o2-comment-rendered', this.updateTaskControls );

			_.bindAll( this, 'onChecklistRequestSuccess', 'onChecklistRequestError', 'onTaskDragStart', 'onTaskDragStop', 'onChecklist550Match', 'onChecklist550Unmatch', 'onBlurTaskEdit', 'updateTaskControls' );

			if ( 'undefined' !== typeof enquire ) {
				enquire.register( 'screen and ( max-width : 550px )', {
					match: this.onChecklist550Match,
					unmatch: this.onChecklist550Unmatch
				} );
			}
		},

		remove: function() {
			if ( 'undefined' !== typeof enquire ) {
				enquire.unregister( 'screen and ( max-width : 550px )' );
			}
		},

		onChecklist550Match: function() {
			this.options.bp550Match = true;
			this.updateTaskControls();
		},

		onChecklist550Unmatch: function() {
			this.options.bp550Match = false;
			this.updateTaskControls();
		},

		onClickTaskText: function( event ) {
			event.stopPropagation();
			// if the user clicked on a link in a task item span, let link takes priority
			if ( 'a' === event.target.tagName.toLowerCase() ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			var _this = this;

			var containingItem = $( event.target ).parents( 'li' ).first();

			// if they clicked on an task item that is already showing its tools, close it
			if ( containingItem.hasClass( 'o2-current-task-item' ) ) {
				containingItem.find( '.o2-task-tools' ).slideUp( 'fast', function() {
					containingItem.removeClass( 'o2-current-task-item' );
				} );
			} else {
				// they clicked on a task item not already showing its tools
				this.$el.find( '.o2-current-task-item' ).find( '.o2-task-tools' ).slideUp( 'fast', function() {
					_this.$el.find( '.o2-current-task-item' ).removeClass( 'o2-current-task-item' );
				} );
				containingItem.find( '.o2-task-tools' ).hide();
				containingItem.addClass( 'o2-current-task-item' );
				containingItem.find( '.o2-task-tools' ).slideDown( 'fast' );
			}
		},

		onClickTaskCheckbox: function( event ) {
			event.stopPropagation();
			var containingItem = $( event.target ).parents( 'li' ).first();
			var isChecked = $( event.target ).is( ':checked' );

			if ( isChecked ) {
				containingItem.addClass( 'o2-task-completed' );
			} else {
				containingItem.removeClass( 'o2-task-completed' );
			}
			this.sendChecklistRequest( containingItem, 'check', isChecked, 0 );
		},

		onClickTaskAdd: function( event ) {
			event.preventDefault();
			event.stopPropagation();
			var containingItem = $( event.target ).parents( 'li' ).first();

			// trigger an onClickTaskEdit on it

			var newTaskItem = containingItem.clone();
			newTaskItem.addClass( 'o2-task-new' );
			newTaskItem.data( 'item-text', '' );
			newTaskItem.removeClass( 'o2-task-completed' ); // in case we're cloning a completed task
			newTaskItem.find( 'input:checkbox' ).attr( 'checked', false );
			containingItem.find( '.o2-task-tools' ).hide();
			containingItem.removeClass( 'o2-current-task-item' );
			containingItem.after( newTaskItem );

			// find it again so that parent() works on it correctly
			newTaskItem = this.$el.find( '.o2-task-new' );
			newTaskItem.addClass( 'o2-current-task-item' );
			newTaskItem.find( '.o2-edit-task' ).click();
		},

		// turn off poll cache processing for now
		// @todo use filtering when it becomes available
		checkListSuspendUpdates: function() {
			this.options.previousSaveInProgress = o2.App.appState.saveInProgress;
			o2.App.appState.saveInProgress = true;
		},

		checkListResumeUpdates: function() {
			o2.App.appState.saveInProgress = this.options.previousSaveInProgress;
		},

		onClickTaskEdit: function( event ) {
			event.preventDefault();
			event.stopPropagation();

			var containingItem = $( event.target ).parents( 'li' ).first();
			var itemText = containingItem.data( 'item-text' );

			// hide the existing text
			// replace it with an input
			containingItem.find( '.o2-task-item-text' ).hide().after( '<input type="text" class="o2-task-item-input" value=""/>' );
			var textInput = containingItem.find( '.o2-task-item-input' );
			textInput.val( itemText );
			textInput.focus();
			containingItem.find( '.o2-task-tools' ).hide();

			this.checkListSuspendUpdates();
		},

		onKeyPressTask: function( event ) {
			if ( 13 === event.keyCode ) {
				event.preventDefault();
				event.stopPropagation();
				this.onSaveTask( event );
			} else if ( 27 === event.keyCode ) {
				event.preventDefault();
				event.stopPropagation();
				this.onCancelTaskEdit( event );
			}
		},

		onSaveTask: function( event ) {
			var containingItem = $( event.target ).parents( 'li' ).first();
			var newItemText = containingItem.find( '.o2-task-item-input' ).val();

			// Since we are saving, ignore the blur that will occur
			// when we remove this element from the DOM
			this.options.ignoreTaskBlur = true;
			containingItem.find( '.o2-task-item-input' ).remove();
			this.options.ignoreTaskBlur = false;

			if ( newItemText.length ) {
				containingItem.find( '.o2-task-item-text' ).html( newItemText ).show();
				var command = containingItem.hasClass( 'o2-task-new' ) ? 'add' : 'update';
				this.sendChecklistRequest( containingItem, command, newItemText, 0 );
				this.checkListResumeUpdates();
			} else {
				this.onCancelTaskEdit( event );
			}
		},

		onBlurTaskEdit: function( event ) {
			event.stopPropagation();
			if ( false === this.options.ignoreTaskBlur ) {
				this.onCancelTaskEdit( event );
			}
		},

		onCancelTaskEdit: function( event ) {
			var containingItem = $( event.target ).parents( 'li' ).first();
			containingItem.find( '.o2-task-item-input' ).remove();
			containingItem.find( '.o2-task-item-text' ).show();
			containingItem.removeClass( 'o2-current-task-item' );

			if ( containingItem.hasClass( 'o2-task-new' ) ) {
				containingItem.remove();
			}
			this.checkListResumeUpdates();
		},

		onClickTaskDelete: function( event ) {
			event.preventDefault();
			event.stopPropagation();
			var containingItem = $( event.target ).parents( 'li' ).first();
			if ( window.confirm( o2.strings.deleteChecklistItem ) ) {
				this.sendChecklistRequest( containingItem, 'delete', 0, 0 );
				containingItem.hide();
			}
		},

		sendChecklistRequest: function( containingItem, command, arg1, arg2 ) {
			var containingForm = containingItem.parents( 'form' ).first();

			var itemData = {
				objectID: containingForm.data( 'object-id' ),
				objectType: containingForm.data( 'object-type' ),
				nonce: o2.options.nonce,
				itemHash: containingItem.data( 'item-hash' ),
				itemHashInstance: containingItem.data( 'hash-instance' ),
				command: command,
				arg1: arg1,
				arg2: arg2
			};

			// dim the containing form for a bit, to give a sense that a save is in progress
			containingForm.css( { opacity: 0.7 } );

			var ajaxURL = containingForm.attr( 'action' );

			this.trigger( 'ignoreEditAction', true ); // ignore edit action on this view until after checklist response returns

			var jqXHR = $.ajax( {
				type:     'POST',
				dataType: 'json',
				url:      ajaxURL,
				xhrFields: {
					withCredentials: true
				},
				data: {
					action: 'o2_checklist',
					data:   itemData
				},
				success: this.onChecklistRequestSuccess,
				error: this.onChecklistRequestError
			} );

			// decorate the request so we can quickly recognize it later
			jqXHR.checklistRequestCount = ++this.options.checklistRequestCount;
		},

		onChecklistRequestSuccess: function( response, textStatus, jqXHR ) {
			this.trigger( 'ignoreEditAction', false ); // re-enable edit action on this view

			if ( 'undefined' === typeof response.success || ! response.success ) {
				var errorText = o2.strings.checklistError;
				if ( 'undefined' !== typeof response.data && 'undefined' !== typeof response.data.errorText ) {
					errorText += ': ' + response.data.errorText;
				}

				o2.Notifications.add( {
					type: 'error',
					text: errorText,
					sticky: true
				} );

				return;
			}

			if ( 'undefined' !== typeof response.data.type && 'undefined' !== typeof response.data.id &&
				'undefined' !== typeof response.data.contentRaw && 'undefined' !== typeof response.data.contentFiltered ) {

				// if this response isn't to the latest request, don't bother updating the model - we
				// don't want to update it just to have another checklist response update it again
				if ( ( 'undefined' === typeof jqXHR.checklistRequestCount ) || ( jqXHR.checklistRequestCount === this.options.checklistRequestCount ) ) {
					this.model.set( {
						contentRaw: response.data.contentRaw,
						contentFiltered: response.data.contentFiltered
					} );
				}
			} else {
				o2.Notifications.add( {
					type: 'error',
					text: o2.strings.checklistError + ': ' + 'A malformed response was received',
					sticky: true
				} );
				console.error( 'error: response = ', response );
			}
		},

		onChecklistRequestError: function( jqXHR, textStatus, errorThrown ) {
			var errorText = o2.strings.checklistError;

			if ( 'undefined' !== typeof textStatus && null != textStatus && textStatus.length ) {
				errorText += ': ' + textStatus;
			} 

			if ( 'undefined' !== typeof errorThrown && null != errorThrown ) {
				errorText += ': ' + errorThrown;
			} else {
				errorText += ': ' + o2.strings.unknownChecklistError;
			}

			o2.Notifications.add( {
				type: 'error',
				text: errorText,
				sticky: true
			} );

			this.render();
			this.trigger( 'ignoreEditAction', false ); // re-enable edit action on this view
		},

		updateTaskControls: function() {
			// If there are no checklists at all, bail early
			if ( 0 === this.$el.find( '.o2-tasks' ).length ) {
				return;
			}

			var _this = this;

			// Now, let's go through each list in this post
			this.$el.find( '.o2-tasks-form' ).each( function() {
				var isNestedList = false;
				var containingForm = $( this );

				// Enable all checkboxes
				containingForm.find( '.o2-task-item' ).find( 'input:checkbox' ).prop( 'disabled', false );

				// Remove delete task from parents and disable parents with unchecked children
				containingForm.find( '.o2-task-item' ).each( function() {
					var taskItem = $( this );
					var childList = taskItem.children( '.o2-tasks' );
					if ( 0 < childList.length ) {
						isNestedList = true;
						// remove the delete link from the parent
						taskItem.find( '.o2-delete-task' ).remove();
						// disable the parent's checkbox if any child is unchecked
						if ( childList.find( 'input:checkbox:not(:checked)' ).length ) {
							taskItem.children( 'input' ).prop( 'disabled', 'disabled' );
						}
					}
				} );

				// Make sure the containing form is 1) has more than one item and 2) is not nested 
				if ( ( containingForm.find( '.o2-task-item' ).length > 1 ) && ( ! isNestedList ) ) {
					// setup sortable
					// use a longer delay on tiny devices to avoid accidental sort triggering when dragging the display
					var sortableDelayMilliseconds = ( _this.options.bp550Match ) ? 2000 : 500;
					containingForm.find( '.o2-tasks' ).each( function() {
						var myList = $( this );
						myList.sortable( {
							placeholder: 'ui-sortable-dropzone',
							start: _this.onTaskDragStart,
							stop: _this.onTaskDragStop,
							items: '> li.o2-task-sortable',
							delay: sortableDelayMilliseconds
						} );
					} );
				}
			} );
		},

		onTaskDragStart: function() {
			this.checkListSuspendUpdates();
		},

		onTaskDragStop: function( event, ui ) {
			this.checkListResumeUpdates();

			var draggedItem = ui.item;

			// OK, we know which item was dragged - find it, and then find the prev (or next) item
			var prevItem = ui.item.prev();
			if ( prevItem.length ) {
				this.sendChecklistRequest( draggedItem, 'moveAfter', prevItem.data( 'item-hash' ), prevItem.data( 'hash-instance' ) );
			} else {
				var nextItem = ui.item.next();
				if ( nextItem.length ) {
					this.sendChecklistRequest( draggedItem, 'moveBefore', nextItem.data( 'item-hash' ), nextItem.data( 'hash-instance' ) );
				}
			}
		}
	};
} )( jQuery );

Cocktail.mixin( o2.Views.Comment, ChecklistsExtendsCommon );
Cocktail.mixin( o2.Views.Post, ChecklistsExtendsCommon );
