
/* global go, Ext */

go.modules.community.history.SystemSettingsPanel = Ext.extend(go.systemsettings.Panel, {

	title: t("History"),
	iconCls: 'ic-history',
	labelWidth: 125,
	initComponent: function () {

		//The account dialog is an go.form.Dialog that loads the current User as entity.
		this.items = [{
			xtype: "fieldset",
			items: [
				{
					xtype: "compositefield",
					items: [{
						xtype: "numberfield",
						decimals: 0,
						fieldLabel: t("Delete entries after"),
						name: "deleteAfterDays"
					}, {
						xtype: "label",
						html: t("days")
					}]
				}]
		}];

		go.modules.community.addressbook.SystemSettingsPanel.superclass.initComponent.call(this);
	}

});

