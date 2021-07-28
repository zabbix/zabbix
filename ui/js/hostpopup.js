const host_popup = {
	init() {
		this.initActionButtons();
	},

	initActionButtons() {
		document.addEventListener('click', event => {
			if (event.target.classList.contains('js-create-host')) {
				const options = (event.target.dataset.hostgroups !== undefined)
					? {groupids: JSON.parse(event.target.dataset.hostgroups)}
					: {};

				const url = new Curl('zabbix.php', false);
				url.setArgument('action', 'host.create');
				history.pushState({}, '', url.getUrl());

				this.edit(options);
			}
			else if (event.target.classList.contains('js-edit-host')) {
				let hostid = null;

				if (event.target.hostid !== undefined && event.target.dataset.hostid !== undefined) {
					hostid = event.target.dataset.hostid;
				}
				else {
					hostid = new Curl(event.target.href).getArgument('hostid')
				}

				this.edit({hostid:  hostid});

				history.pushState({}, '', event.target.getAttribute('href'));

				event.preventDefault();
			}
		}, {capture: true});
	},

	edit(options = {}) {
		this.pauseRefresh();

		const overlay = PopUp('popup.host.edit', options, 'host_edit', document.activeElement);

		overlay.$dialogue[0].addEventListener('dialogue.submit', (e) => {
			postMessageOk(e.detail.title);

			if (e.detail.messages !== null) {
				postMessageDetails('success', e.detail.messages);
			}

			// reload || refresh;
		});

		overlay.$dialogue[0].addEventListener('overlay.close', () => this.resumeRefresh(), {once: true});
	},

	pauseRefresh() {},

	resumeRefresh() {}

};
