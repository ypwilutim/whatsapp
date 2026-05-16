(() => {
    const state = {
        currentChatId: null,
        currentCustomerId: null,
        currentChatNum: '',
        currentCustomerName: '',
        pollingInterval: null,
    };

    const els = {
        customerList:    document.getElementById('customerList'),
        messagesCont:    document.getElementById('messagesContainer'),
        chatHeader:      document.getElementById('chatHeader'),
        inputArea:       document.getElementById('inputArea'),
        sendForm:        document.getElementById('sendMessageForm'),
        messageInput:    document.getElementById('messageInput'),
        hiddenChatId:    document.getElementById('currentChatId'),
        hiddenNumber:    document.getElementById('currentCustomerNumber'),
        searchInput:     document.getElementById('searchCustomer'),
    };

    // ===================== HELPERS =====================
    function apiFetch(url, opts = {}) {
        return fetch(url, { ...opts, credentials: 'same-origin' }).then(r => r.json());
    }

    function formatTime(dt) {
        const d = new Date(dt);
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(dt) {
        const d = new Date(dt);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    }

    // ===================== MOBILE — show / hide chat area =====================
    function openChatArea() {
        document.querySelector('.chat-area')?.classList.add('active');
        document.querySelector('.mobile-backdrop')?.classList.add('active');
    }
    function closeChatArea() {
        document.querySelector('.chat-area')?.classList.remove('active');
        document.querySelector('.mobile-backdrop')?.classList.remove('active');
    }

    // Remove backdrop on click
    document.addEventListener('click', e => {
        if (e.target.classList.contains('mobile-backdrop')) closeChatArea();
    });

    // ===================== CUSTOMER LIST =====================
    function handleCustomerClick(el) {
        document.querySelectorAll('.customer-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');

        state.currentChatId    = el.dataset.chatId;
        state.currentCustomerId = el.dataset.customerId || el.dataset.customer_id || '';
        state.currentChatNum   = el.dataset.customerNumber || '';
        state.currentCustomerName = el.dataset.name || '';

        els.hiddenChatId.value   = state.currentChatId;
        els.hiddenNumber.value   = state.currentChatNum;
        els.inputArea.style.display = 'flex';

        openChatArea();
        renderHeader(el);
        loadMessages();
        startPolling();

        const badge = el.querySelector('.unread-badge');
        if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }
    }

    function renderHeader(el) {
        const name    = state.currentCustomerName || (el.dataset.name || el.querySelector('.customer-name')?.textContent?.trim() || 'Customer').replace(/\s*<[^>]+>/g, '');
        const num     = state.currentChatNum    || el.dataset.customerNumber || el.querySelector('.customer-number')?.textContent?.trim() || '';
        const unread  = (el.querySelector('.unread-badge')?.textContent || '0');

        els.chatHeader.innerHTML = `
            <button class="btn btn-back" onclick="window.history.back()" style="margin-right:8px;display:none;background:none;border:none;font-size:18px;">&#8592;</button>
            <div class="chat-avatar" style="cursor:default">${escapeHtml(name).substring(0,2).toUpperCase()}</div>
            <div style="cursor:pointer" title="Klik untuk lihat riwayat" onclick="showCustomerHistory('${escapeHtml(num)}','${escapeHtml(name)}')">
                <h2 style="margin:0;font-size:15px;">${escapeHtml(name)}</h2>
                <small style="color:#888;cursor:pointer;text-decoration:underline">${escapeHtml(num)}</small>
                ${unread !== '0' ? '<span class="unread-badge" style="margin-left:8px;">' + unread + '</span>' : ''}
            </div>
            <button class="btn btn-close-chat" title="Tutup Chat" onclick="closeChat()" style="margin-left:auto;background:#ef5350;color:#fff;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px;">Tutup</button>
        `;
    }

    function showCustomerHistory(num, name) {
        const url = 'dashboard.php?action=get_customer_history&customer_id=' + encodeURIComponent(state.currentCustomerId);
        apiFetch(url)
            .then(res => {
                if (res.status === 'success' && res.histories) {
                    const total = res.histories.length;
                    alert('Riwayat percakapan dengan ' + name + ':\n' + total + ' kali chat\n\nLihat detail di database.');
                }
            })
            .catch(() => alert('Gagal mengambil riwayat'));
    }

    window.closeChat = function () {
        if (!confirm('Tutup percakapan ini?')) return;
        const fd = new FormData();
        fd.append('chat_id', state.currentChatId);
        apiFetch('dashboard.php?action=close_chat', { method: 'POST', body: fd })
            .then(res => {
                if (res.status === 'success') {
                    els.inputArea.style.display = 'none';
                    els.messagesCont.innerHTML = '<div class="empty-chat"><div class="empty-chat-icon">&#10003;</div><p>Chat ditutup</p></div>';
                    closeChatArea();
                    setTimeout(loadChats, 500);
                } else alert(res.message);
            });
    };

    // ===================== EVENT DELEGATION =====================
    els.customerList?.addEventListener('click', e => {
        const item = e.target.closest('.customer-item');
        if (item) handleCustomerClick(item);
    });

    // ===================== SEND MESSAGE =====================
    els.sendForm?.addEventListener('submit', e => {
        e.preventDefault();

        const chatId  = els.hiddenChatId.value;
        const number  = els.hiddenNumber.value;
        const message = els.messageInput.value.trim();

        if (!chatId || !number || !message) return;

        const btn = els.sendForm.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = '...';

        const formData = new FormData();
        formData.append('chat_id', chatId);
        formData.append('number',  number);
        formData.append('message', message);

        apiFetch('api/send_message.php', {
            method: 'POST',
            body:   formData,
        })
        .then(res => {
            if (res.status === 'success') {
                appendMessage(res.created_at, 'sent', message);
                els.messageInput.value = '';
                els.messageInput.style.height = 'auto';
                loadChats();
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
            }
        })
        .catch(err => console.error(err))
        .finally(() => { btn.disabled = false; btn.textContent = 'Kirim'; });
    });

    // ===================== MESSAGES =====================
    function loadMessages() {
        const chatId = state.currentChatId;
        if (!chatId) return;

        apiFetch('api/get_messages.php?chat_id=' + chatId)
            .then(res => {
                if (res.status === 'success' && Array.isArray(res.messages)) {
                    // Cek pesan baru
                    const existingIds = new Set(
                        Array.from(els.messagesCont.querySelectorAll('.msg-wrapper'))
                            .map(el => parseInt(el.dataset.msgId || '0'))
                    );

                    if (existingIds.size === res.messages.length) {
                        // Cek apakah ada pesan baru tiba
                        const latestFromServer = res.messages[res.messages.length - 1];
                        const latestLocal = els.messagesCont.querySelector('.msg-wrapper:last-child');
                        if (latestLocal && parseInt(latestLocal.dataset.msgId) === parseInt(latestFromServer.id)) {
                            return; // tidak ada pesan baru
                        }
                    }

                    els.messagesCont.innerHTML = '';
                    let anyUnread = false;
                    res.messages.forEach(m => {
                        // Konversi sender_type DB ('customer'/'agent') → CSS class ('received'/'sent')
                        const stype = m.sender_type === 'agent' ? 'sent' : 'received';
                        appendMessage(m.created_at, stype, m.pesan, m.id);
                        if (m.sender_type === 'customer' && m.is_wa_sent == 0) anyUnread = true;
                    });
                    scrollToBottom();

                    if (anyUnread) {
                        loadChats();
                    }
                }
            })
            .catch(err => console.error('loadMessages:', err));
    }

    function appendMessage(createdAt, senderType, text, msgId) {
        const wrapper = document.createElement('div');
        wrapper.className = `msg-wrapper ${senderType}`;
        wrapper.dataset.msgId = msgId || Math.random().toString(36).substr(2, 9);
        wrapper.innerHTML = `
            <div class="msg-bubble ${senderType}">
                ${text}
                <div class="msg-meta">
                    <span class="msg-time">${formatTime(createdAt)}</span>
                    ${senderType === 'sent' ? '<span class="msg-status">✓✓</span>' : ''}
                </div>
            </div>
        `;
        els.messagesCont.appendChild(wrapper);
        scrollToBottom();
    }

    function scrollToBottom() {
        els.messagesCont.scrollTop = els.messagesCont.scrollHeight;
    }

    // ===================== LOAD CHATS =====================
    function loadChats() {
        apiFetch('api/get_chats.php')
            .then(res => {
                if (res.status === 'success' && res.chats) {
                    const activeId = state.currentChatId;
                    const currentUnread = parseInt(
                        (document.querySelector('.customer-item.active .unread-badge') || {}).textContent || '0'
                    );

                    if (res.chats.length > 0) {
                        els.customerList.innerHTML = res.chats.map(chat => {
                            const isNewUnread = chat.unread_count > 0 && activeId != chat.id;
                            const badgeClass = isNewUnread ? 'unread-badge-new' : 'unread-badge';
                            return `
                            <div class="customer-item${activeId == chat.id ? ' active' : ''}"
                                 data-chat-id="${chat.id}"
                                 data-customer-id="${chat.customer_id}"
                                 data-customer-number="${escapeHtml(chat.nomor_wa)}"
                                 data-name="${escapeHtml(chat.nama || chat.nomor_wa)}"
                                 data-unread-count="${chat.unread_count}">
                                <div class="customer-avatar">${escapeHtml((chat.nama || chat.nomor_wa || '').substring(0,2).toUpperCase())}</div>
                                <div class="customer-info">
                                    <div class="customer-name">
                                        ${escapeHtml(chat.nama || 'Tanpa Nama')}
                                        <span class="customer-number">${escapeHtml(chat.nomor_wa)}</span>
                                    </div>
                                    <div class="customer-last-msg">
                                        ${escapeHtml(chat.last_message ? chat.last_message.substring(0, 42) : 'Belum ada pesan')}
                                    </div>
                                </div>
                                ${chat.unread_count > 0 ?
                                    '<span class="unread-badge' + (isNewUnread ? ' unread-badge-flash' : '') + '">' + chat.unread_count + '</span>' : ''}
                            </div>
                        `}).join('');
                    } else {
                        els.customerList.innerHTML = '<div class="empty-state"><p>Belum ada chat aktif</p><small>Chat akan muncul otomatis saat ada pesan masuk</small></div>';
                    }
                }
            })
            .catch(err => console.error('loadChats:', err));
    }

    // ===================== POLLING =====================
    function startPolling() {
        clearInterval(state.pollingInterval);
        state.pollingInterval = setInterval(() => {
            loadMessages();
            loadChats();
        }, 3000);
    }

    // ===================== SEARCH =====================
    els.searchInput?.addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('.customer-item').forEach(item => {
            const name = (item.dataset.name || '').toLowerCase();
            const num  = (item.dataset.customerNumber || '').toLowerCase();
            item.style.display =
                (name.includes(q) || num.includes(q)) ? '' : 'none';
        });
    });

    // ===================== AUTOSIZE TEXTAREA =====================
    els.messageInput?.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Enter kirim, Shift+Enter baris baru
    els.messageInput?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            els.sendForm.dispatchEvent(new Event('submit'));
        }
    });

    // ===================== INIT =====================
    loadChats();

    if (state.currentChatId) {
        handleCustomerClick(document.querySelector('.customer-item'));
    }
})();
