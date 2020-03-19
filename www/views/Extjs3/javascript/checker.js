GO.Checker = Ext.extend(Ext.util.Observable, {
	lastCount : 0,
	params:{
		requests: {
			reminders: {r:"reminder/store"},
			loginstatus:{r:"core/auth/checkclient"}
		}
	},

	initComponent: function() {
		this.addEvents({'alert' : true});
		GO.Checker.superclass.initComponent.call(this);
	},

	callbacks : {},
	
	init : function(){

		Ext.TaskMgr.start({
			run: this.checkForNotifications,
			scope:this,
			interval: GO.settings.config.checker_interval*1000,
						// interval: 10000 // debug / test config
		});
		this.initReminders();
	},

	initReminders: function() {

		go.Notifier.addStatusIcon('reminder', 'ic-notifications');
		var checkerSnoozeTimes = [
			[300,'5 '+t("Minutes")],
			[600, '10 '+t("Minutes")],
			[1200, '20 '+t("Minutes")],
			[1800, '30 '+t("Minutes")],
			[3600, '1 '+t("Hour")],
			[7200, '2 '+t("Hours")],
			[10800, '3 '+t("Hours")],
			[14400, '4 '+t("Hours")],
			[86400, '1 '+t("Day")],
			[2*86400, '2 '+t("Days")],
			[3*86400, '3 '+t("Days")],
			[4*86400, '4 '+t("Days")],
			[5*86400, '5 '+t("Days")],
			[6*86400, '6 '+t("Days")],
			[7*86400, '7 '+t("Days")]
		];
		var snoozeMenuItems = [];
		for(var i = 0; i < checkerSnoozeTimes.length; i++){
			snoozeMenuItems.push(	{
				text: checkerSnoozeTimes[i][1],
				value: checkerSnoozeTimes[i][0],
				handler:function(i){ this.doTask(i.value); },
				scope: this
			});
		}
		var snoozeMenu = new Ext.menu.Menu({
			items:snoozeMenuItems
		});

		this.reminders = new Ext.Container({cls: 'notifications'});
		this.reminderStore = new Ext.data.GroupingStore({
			reader: new Ext.data.JsonReader({
				totalProperty: "count",
				root: "results",
				fields:['id','name','description','model_id','model_name','model_type_id',
					'type','local_time', 'iconCls','time','snooze_time','text']
			}),
			groupField: 'type',
			remoteSort: true,
			remoteGroup: true
		});
		this.reminderStore.on('load',function(store, records) {
			this.reminders.removeAll();
			for(var i = 0 ; i < records.length; i++) {
				var ico = records[i].data.iconCls.split('\\').pop();

				this.reminders.add(new Ext.Panel({
					record: records[i],
					title: records[i].data.name,
					iconCls: 'entity '+ico,
					items: [
						{xtype:'box',html:'<b>'+records[i].data.description+'</b><span>'+records[i].data.local_time+'</span>'}
						//{html:records[i].data.description}
					],
					listeners: {
						'afterrender': function(me) {
							me.body.on('click',function (el){
								var record = me.record.data;
								if(!record.model_name || !record.model_id) {
									return;
								}
								var parts = record.model_name.split("\\");
								go.Router.goto(parts[3].toLowerCase()+"/"+record.model_id);
							});
						}
					},
					buttonAlign: 'left',
					buttons: [{
						iconCls : 'ic-timer',
						text: t("Snooze"),
						menu: snoozeMenu
					},{
						iconCls : 'ic-delete',
						text:t("Dismiss"),
						handler: function() {
							this.doTask();
						}
					}]
				}))

			}
			this.reminders.doLayout();
		},this);

		go.Notifier.notificationArea.add(this.reminders);

	},

	doTask : function(task, seconds, reminderIds) {
		Ext.Ajax.request({
			url: seconds ? GO.url('reminder/snooze') : GO.url('reminder/dismiss'),
			params: {
				task:task,
				snooze_time: seconds,
				reminders: Ext.encode(reminderIds)
			},
			callback: function(){
				for (var i = 0; i < reminderIds.length;  i++) {
					this.store.remove(reminderIds[i]);
				}
				GO.checker.lastCount = this.store.getCount();

				if(!GO.checker.lastCount){
					this.ownerCt.hide();
					go.Notifier.icons['reminder'].setVisible(false);
				}
			}, scope: this
		});
	},
  
	// See modules/email/EmailClient.js and search for "GO.checker.registerRequest" for an usage example
	registerRequest : function(url, params, callback, scope){
		params.r=url;
		var requestId = Ext.id();

		this.params.requests[requestId] = params;	
		this.callbacks[requestId] = {
			callback:callback,
			scope:scope
		};
	},
  
	// Function to check for reminders in the database
	checkForNotifications : function(){

		Ext.Ajax.request({
			url: GO.url('core/multiRequest'),	  
			params: {
				requests: Ext.encode(this.params.requests)
			},
			success: function(response) {
				var result = Ext.decode(response.responseText);

				for(var id in result){
					switch(id) {
						case 'reminders':
							this.handleReminderResponse(result[id]);
							break;
						case 'loginstatus':
							this.handleLoginstatusResponse(result[id]);
							break;
					}
					if (id!='success' && id!='feedback' && this.callbacks[id]) {
						this.callbacks[id].callback.call(this.callbacks[id].scope, this, result[id]);
					}
				}
			},
			scope:this
		});
	},

	handleReminderResponse : function(storeData){
//		this.fireEvent('check', this, data);
		var hasReminders = (storeData.total && storeData.total > 0);
		go.Notifier.icons['reminder'].setVisible(hasReminders);

		if(!hasReminders) return;

		this.reminderStore.loadData(storeData);

		if(this.lastCount == this.reminderStore.getCount()) {
			return;
		}

		this.lastCount = this.reminderStore.getCount();

		if(!GO.util.empty(GO.settings.popup_reminders)){
			if (!("Notification" in window)) {
				if(GO.util.isMobileOrTablet()) {
					return;
				}
				GO.reminderPopup = GO.util.popup({
					width:400,
					height:400,
					url:GO.url("reminder/display"),
					target:'groupofficeReminderPopup',
					position:'br',
					closeOnFocus:false
				});
			} else {
				var text = '';

				for (var i = 0, l = storeData.results.length; i < l; i++) {
					var rem = storeData.results[i];
					text += rem.type+': '+rem.name+' ['+rem.time+']';
				}

				go.Notifier.notify(text, t("Reminders"));
			}
		}
		go.Notifier.playSound('message-new-email', 'reminder');

	},
	
	handleLoginstatusResponse : function(data){
		// If the login is not valid anymore, then the user is logged out and the browser will be redirected to the login screen
		if(!data.loginValid){     
			document.location.href=BaseHref;
		}
	}
});