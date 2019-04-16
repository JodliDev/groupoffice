
/* global go, Ext */

go.links.LinkBrowser = Ext.extend(go.Window, {
	entity: null,
	entityId: null,
	
	stateId: "go-link-browser",
	
	layout: "border",
	maximizable: true,
	

	initComponent: function () {

		var actions = this.initRowActions();
		
		this.entityGrid = new go.links.EntityGrid({
			width: dp(200),
			region: "west",
			split: true
		});

		this.entityGrid.getSelectionModel().on('selectionchange', function (sm) {
			this.store.baseParams.filter.entities = sm.getSelections().map(function(r){return {name: r.data.entity, filter: r.data.filter};});
			this.store.load();
		}, this, {buffer: 1});


		this.store = new go.data.GroupingStore({
			autoDestroy: true,
			remoteGroup: true,
			fields: ['id', 'toId', 'toEntity', {name: "to", type: "Search", key: "toSearchId"}, 'description', {name: 'modifiedAt', type: 'date'}],
			entityStore: "Link",
			sortInfo: {field: 'toEntity', direction: 'DESC'},
			autoLoad: true,
			groupOnSort: true,
			groupField: 'toEntity',
			baseParams: {
				filter: 
					{
						entity: this.entity,
						entityId: this.entityId
					}
			}
		});

		this.grid = new go.grid.GridPanel({
			cls: "go-link-grid",
			region: "center",
			plugins: [actions],
			tbar: [		
				'->',
				{
					xtype: 'tbsearch'
				}			
			],
			store: this.store,
			view: new Ext.grid.GroupingView({
				hideGroupedColumn: true,
				forceFit: true,
				// custom grouping text template to display the number of items per group
				groupTextTpl: '{text} ({[values.rs.length]} {[values.rs.length > 1 ? "Items" : "Item"]})'
			}),
			columns: [
				{
					id: 'name',
					header: t('Name'),
					width: 75,
					sortable: true,
					dataIndex: 'name',
					renderer: function (value, metaData, record, rowIndex, colIndex, store) {

						var str = record.data.to.name + " <br /><label>" + record.data.to.description + "</label>";

						var linkIconCls = go.Entities.getLinkIcon(record.data.toEntity, record.data.to.filter);
						
						if (rowIndex === 0 || this.lastLinkIconCls != linkIconCls) {
							str = '<i class="entity ' + linkIconCls + '"></i>' + str;
							
							this.lastLinkIconCls = linkIconCls;
						}

						return str;
					}
				}, {
					id: 'toEntity',
					header: t('Type'),
					width: 75,
					sortable: true,
					dataIndex: 'toEntity',
					renderer: function(v) {
						return t(v, go.Entities.get(v).module);
					}
				},
				{
					id: 'modifiedAt',
					header: t('Modified at'),
					width: 160,
					hidden: true,
					sortable: true,
					dataIndex: 'modifiedAt',
					renderer: Ext.util.Format.dateRenderer(go.User.dateTimeFormat)
				},
				actions
			],
			listeners: {
				navigate: function(grid, index, record) {				
					this.load(record.data.toEntity, record.data.toId);						
				},
				
//				dblclick: function () {
//					var record = this.grid.getSelectionModel().getSelected();
//					var entity = go.Entities.get(record.data.toEntity);
//
//					if (!entity) {
//						throw record.data.toEntity + " is not a registered entity";
//					}
//					
//					entity.goto(record.data.toId);
//					
//					this.close();
//				},
				scope: this
			},
			autoExpandColumn: 'name'			
		});

		Ext.apply(this, {
			title: t("Links"),
			width: dp(1200),
			height: dp(600),
			layout: 'border',
			items: [this.entityGrid , this.grid, this.getPreviewPanel()]
		});

		go.links.CreateLinkWindow.superclass.initComponent.call(this);
	},
	
	load : function(entity, id) {
		var pnl = this.previewPanel.getComponent(entity);
		if(pnl) {
			pnl.load(id);

			this.previewPanel.getLayout().setActiveItem(pnl);
		}
	},
	
	getPreviewPanel : function() {
		
		var all = go.Entities.getLinkConfigs().filter(function(l) {
			return !!l.linkDetail;
		});
		
		var items = all.map(function(e) {
			var panel = e.linkDetail();
			panel.itemId = e.entity;
			
			return panel;
		});
		
		console.log(items);
		
		return this.previewPanel = new Ext.Panel({
			split: true,
			region: "east",
			width: dp(500),
			layout:"card",
			items: items
		});
	},

	initRowActions: function () {

		var actions = new Ext.ux.grid.RowActions({
			menuDisabled: true,
			hideable: false,
			draggable: false,
			fixed: true,
			header: '',
			hideMode: 'display',
			keepSelection: true,

			actions: [{
					iconCls: 'btn-delete ux-row-action-on-hover',
					qtip: t("Add")
				}]
		});

		actions.on({
			action: function (grid, record, action, row, col, e, target) {
				go.Db.store("Link").set({
					destroy: [record.id]
				});
			}
		});

		return actions;

	}
});


