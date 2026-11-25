/*
 * Adminlə yazışma: mesaj göndərmək (səhifə yenilənmədən) və yeni mesajları
 * bir neçə saniyədən bir soruşmaq. JS işləməsə, forma adi POST kimi göndərilir.
 *
 * Səhifədə [data-support-chat] blokları avtomatik işə düşür.
 * data-lazy="1" olan blok (sağ aşağı küncdəki pəncərə) yalnız açılanda yüklənir: SupportChat.get(el).start()
 */
(function () {
    var POLL_MS = 6000;
    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    function Chat(root) {
        this.root = root;
        this.box = root.querySelector('.chat-box');
        this.form = root.querySelector('.chat-form');
        this.input = root.querySelector('textarea');
        this.errorEl = root.querySelector('.chat-error');
        this.empty = root.querySelector('.chat-empty');
        this.sendBtn = this.form.querySelector('button[type="submit"]');
        this.after = parseInt(root.dataset.after, 10) || 0;
        this.started = false;
        this.polling = false;
        this.timer = null;
        this.onMessages = null; // xarici dinləyici (məs. küncdəki pəncərənin nişanı)
        this.bind();
    }

    Chat.prototype.scrollDown = function () { this.box.scrollTop = this.box.scrollHeight; };

    Chat.prototype.append = function (m) {
        if (this.box.querySelector('[data-id="' + m.id + '"]')) return false; // göndərmə və soruşma eyni mesajı gətirə bilər
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg ' + (m.mine ? 'mine' : 'theirs');
        wrap.dataset.id = m.id;
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.textContent = m.body;
        var meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.textContent = (m.author ? m.author + ' · ' : '') + m.time;
        if (m.mine) {
            // WhatsApp kimi: ✓ — göndərilib, ✓✓ — qarşı tərəf oxuyub
            var ticks = document.createElement('span');
            ticks.innerHTML = '<i class="fa"></i>';
            meta.appendChild(ticks);
            setTicks(ticks, m.read);
        }
        wrap.appendChild(bubble);
        wrap.appendChild(meta);
        this.box.appendChild(wrap);
        if (this.empty) this.empty.hidden = true;
        if (m.id > this.after) this.after = m.id;
        return true;
    };

    /** "Admin onlayndır" yazısı və küncdəki düymənin yaşıl nöqtəsi */
    function setOnline(on) {
        document.querySelectorAll('[data-chat-status]').forEach(function (el) {
            el.classList.toggle('online', !!on);
            el.querySelector('.chat-status-text').textContent = on ? el.dataset.on : el.dataset.off;
        });
        // küncdəki düymənin halqası: admin saytdadırsa yaşıl
        document.querySelectorAll('[data-online-ring]').forEach(function (f) {
            f.classList.toggle('is-online', !!on);
            f.title = on ? 'Admin onlayndır' : '';
        });
    }

    function setTicks(el, read) {
        el.className = 'chat-ticks' + (read ? ' read' : '');
        el.title = read ? 'Oxunub' : 'Göndərilib';
        el.firstChild.className = 'fa ' + (read ? 'fa-check-double' : 'fa-check');
    }

    /** Qarşı tərəf bu id-yə qədər olan öz mesajlarımı oxuyub — ✓ → ✓✓ */
    Chat.prototype.markRead = function (until) {
        if (!until) return;
        this.box.querySelectorAll('.chat-msg.mine').forEach(function (el) {
            var t = el.querySelector('.chat-ticks');
            if (t && !t.classList.contains('read') && parseInt(el.dataset.id, 10) <= until) setTicks(t, true);
        });
    };

    Chat.prototype.showError = function (text) {
        this.errorEl.textContent = text || '';
        this.errorEl.hidden = !text;
    };

    Chat.prototype.poll = function () {
        var self = this;
        if (self.polling || document.hidden || !self.isVisible()) return Promise.resolve();
        self.polling = true;
        return fetch(self.root.dataset.poll + '?after=' + self.after, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
              if (!data || !data.messages) return;
              var nearBottom = self.box.scrollHeight - self.box.scrollTop - self.box.clientHeight < 120;
              var added = data.messages.filter(function (m) { return self.append(m); });
              self.markRead(data.read_until);
              if (typeof data.admin_online === 'boolean') setOnline(data.admin_online);
              if (added.length && nearBottom) self.scrollDown();
              if (self.onMessages) self.onMessages(added);
          })
          .catch(function () {})
          .then(function () { self.polling = false; });
    };

    /** Pəncərə bağlıdırsa, soruşmağa ehtiyac yoxdur */
    Chat.prototype.isVisible = function () {
        return this.root.offsetParent !== null;
    };

    Chat.prototype.start = function () {
        var self = this;
        if (self.started) { self.poll(); return; }
        self.started = true;
        if (self.empty && self.root.dataset.lazy) self.empty.hidden = true; // yüklənənə qədər "boşdur" yazısı görünməsin
        self.poll().then(function () {
            if (self.empty && !self.box.querySelector('.chat-msg')) self.empty.hidden = false;
            self.scrollDown();
        });
        self.timer = setInterval(function () { self.poll(); }, POLL_MS);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) self.poll(); });
    };

    Chat.prototype.bind = function () {
        var self = this;
        self.form.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = self.input.value.trim();
            if (!body) { self.showError('Mesajı yazın.'); return; }
            self.sendBtn.disabled = true;
            self.showError('');
            fetch(self.root.dataset.post, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ body: body })
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
            }).then(function (res) {
                if (res.ok && res.data.message) {
                    self.append(res.data.message);
                    self.input.value = '';
                    self.scrollDown();
                    return;
                }
                if (res.status === 429) { self.showError('Çox tez-tez göndərirsiniz. Bir az gözləyin.'); return; }
                if (res.status === 419) { self.showError('Səhifənin vaxtı keçib. Səhifəni yeniləyin.'); return; }
                var errs = res.data.errors && res.data.errors.body;
                self.showError(errs ? errs[0] : (res.data.message || 'Mesaj göndərilmədi. Yenidən cəhd edin.'));
            }).catch(function () {
                self.showError('Bağlantı xətası. Yenidən cəhd edin.');
            }).then(function () {
                self.sendBtn.disabled = false;
                self.input.focus();
            });
        });

        // Enter — göndər, Shift+Enter — yeni sətir
        self.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                if (self.form.requestSubmit) self.form.requestSubmit();
                else self.form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    };

    var instances = [];
    window.SupportChat = {
        setOnline: setOnline,
        get: function (el) {
            for (var i = 0; i < instances.length; i++) if (instances[i].root === el) return instances[i];
            return null;
        }
    };

    document.querySelectorAll('[data-support-chat]').forEach(function (el) {
        var chat = new Chat(el);
        instances.push(chat);
        if (!el.dataset.lazy) {
            chat.scrollDown();
            chat.start();
        }
    });
})();
