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
        this.fileInput = this.form.querySelector('input[type="file"]'); // yalnız admin söhbətində
        this.picked = root.querySelector('.chat-file-picked');
        this.after = parseInt(root.dataset.after, 10) || 0;
        this.started = false;
        this.polling = false;
        this.timer = null;
        this.onMessages = null; // xarici dinləyici (məs. küncdəki pəncərənin nişanı)
        this.bind();
    }

    Chat.prototype.scrollDown = function () { this.box.scrollTop = this.box.scrollHeight; };

    Chat.prototype.clearFile = function () {
        if (!this.fileInput) return;
        this.fileInput.value = '';
        if (this.picked) this.picked.hidden = true;
    };

    /** Mesajdakı fayl: şəkil — kiçik şəkil (basanda böyük açılır), qalanı — ad və ölçü ilə yükləmə linki */
    function fileEl(f) {
        if (f.video) { // video: söhbətdə oynadılır, ⤢ düyməsi ilə böyük ekranda açılır
            var box = document.createElement('div');
            box.className = 'chat-file-video';
            var v = document.createElement('video');
            v.src = f.url; v.controls = true; v.preload = 'metadata';
            v.setAttribute('playsinline', '');
            var full = document.createElement('button');
            full.type = 'button'; full.className = 'chat-video-full'; full.setAttribute('aria-label', 'Böyük ekranda aç');
            full.innerHTML = '<i class="fa fa-expand"></i>';
            box.appendChild(v); box.appendChild(full);
            return box;
        }
        var a = document.createElement('a');
        a.href = f.url;
        if (f.image) {
            a.className = 'chat-file-img';
            a.target = '_blank';
            a.rel = 'noopener';
            var img = document.createElement('img');
            img.src = f.url; img.alt = f.name; img.loading = 'lazy';
            a.appendChild(img);
        } else {
            a.className = 'chat-file';
            a.setAttribute('download', f.name);
            var ico = document.createElement('i');
            ico.className = 'fa fa-file-alt';
            var name = document.createElement('span');
            name.className = 'chat-file-name';
            name.textContent = f.name;
            var size = document.createElement('small');
            size.textContent = f.size;
            a.appendChild(ico); a.appendChild(name); a.appendChild(size);
        }
        return a;
    }

    /** Mətndəki http(s):// ünvanları basıla bilən link edir (HTML yox, yalnız mətn və <a> düyünləri) */
    function linkify(el, text) {
        var re = /https?:\/\/[^\s<>"']+/g, pos = 0, m;
        while ((m = re.exec(text))) {
            var url = m[0].replace(/[.,!?:;)]+$/, '');
            el.appendChild(document.createTextNode(text.slice(pos, m.index)));
            var a = document.createElement('a');
            a.href = url; a.target = '_blank'; a.rel = 'noopener nofollow';
            a.textContent = url;
            el.appendChild(a);
            pos = m.index + url.length;
            re.lastIndex = pos;
        }
        el.appendChild(document.createTextNode(text.slice(pos)));
    }

    Chat.prototype.append = function (m) {
        if (this.box.querySelector('[data-id="' + m.id + '"]')) return false; // göndərmə və soruşma eyni mesajı gətirə bilər
        var wrap = document.createElement('div');
        wrap.className = 'chat-msg ' + (m.mine ? 'mine' : 'theirs');
        wrap.dataset.id = m.id;
        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        if (m.file) bubble.appendChild(fileEl(m.file));
        linkify(bubble, m.body);
        if (m.link) { // admin mesajındakı düymə
            var a = document.createElement('a');
            a.className = 'chat-link-btn';
            a.href = m.link.url;
            if (m.link.external) { a.target = '_blank'; a.rel = 'noopener nofollow'; }
            a.textContent = m.link.label + ' ';
            var arrow = document.createElement('i');
            arrow.className = 'fa fa-arrow-right ms-1';
            a.appendChild(arrow);
            bubble.appendChild(a);
        }
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
            var file = self.fileInput && self.fileInput.files[0];
            if (!body && !file) { self.showError(self.fileInput ? 'Mesajı yazın və ya fayl əlavə edin.' : 'Mesajı yazın.'); return; }
            self.sendBtn.disabled = true;
            self.showError('');
            var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' };
            var payload;
            if (file) { // faylla: multipart (Content-Type-ı brauzer özü qoyur)
                payload = new FormData();
                payload.append('body', body);
                payload.append('file', file);
            } else {
                headers['Content-Type'] = 'application/json';
                payload = JSON.stringify({ body: body });
            }
            fetch(self.root.dataset.post, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers,
                body: payload
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
            }).then(function (res) {
                if (res.ok && res.data.message) {
                    self.append(res.data.message);
                    self.input.value = '';
                    self.clearFile();
                    self.scrollDown();
                    return;
                }
                if (res.status === 429) { self.showError('Çox tez-tez göndərirsiniz. Bir az gözləyin.'); return; }
                if (res.status === 413) { self.showError('Fayl çox böyükdür.'); return; }
                if (res.status === 419) { self.showError('Səhifənin vaxtı keçib. Səhifəni yeniləyin.'); return; }
                var errs = res.data.errors && (res.data.errors.file || res.data.errors.body);
                self.showError(errs ? errs[0] : (res.data.message || 'Mesaj göndərilmədi. Yenidən cəhd edin.'));
            }).catch(function () {
                self.showError('Bağlantı xətası. Yenidən cəhd edin.');
            }).then(function () {
                self.sendBtn.disabled = false;
                self.input.focus();
            });
        });

        // seçilmiş fayl: adı göstərilir, × ilə silinir
        if (self.fileInput) {
            self.fileInput.addEventListener('change', function () {
                var f = self.fileInput.files[0];
                if (self.picked) {
                    self.picked.hidden = !f;
                    self.picked.querySelector('span').textContent = f ? f.name : '';
                }
                if (f) self.input.focus();
            });
            var clear = self.picked && self.picked.querySelector('.chat-file-clear');
            if (clear) clear.addEventListener('click', function () { self.clearFile(); });
        }

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

    // videonu böyük ekranda açmaq (söhbətə sonradan gələn mesajlar üçün də — hadisə sənəddə tutulur)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.chat-video-full');
        if (!btn) return;
        var v = btn.parentNode.querySelector('video');
        if (!v) return;
        if (v.requestFullscreen) v.requestFullscreen().catch(function () {});
        else if (v.webkitEnterFullscreen) v.webkitEnterFullscreen(); // iPhone Safari
        else if (v.webkitRequestFullscreen) v.webkitRequestFullscreen();
        v.play && v.play().catch(function () {});
    });

    document.querySelectorAll('[data-support-chat]').forEach(function (el) {
        var chat = new Chat(el);
        instances.push(chat);
        if (!el.dataset.lazy) {
            chat.scrollDown();
            chat.start();
        }
    });
})();
