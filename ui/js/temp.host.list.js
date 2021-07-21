const host_list = {
	init() {
		this.initActionButtons();
	},

	initActionButtons() {
		document.addEventListener('click', event => {
			if (event.target.classList.contains('js-create-host')) {
				const options = (event.target.dataset.hostgrops !== undefined)
					? {hostgrops: [event.target.dataset.hostgrops]}
					: {};

				const url = new Curl('zabbix.php', false);
				url.setArgument('action', 'host.edit');
				history.pushState({}, '', url.getUrl());

				this.edit(options);
			}
			else if (event.target.classList.contains('js-edit-host')) {
				this.edit({hostid: event.target.dataset.hostid});

				history.pushState({}, '', event.target.getAttribute('href'));

				event.preventDefault();
			}
		}, true);
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
