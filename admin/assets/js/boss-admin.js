(function(root){
'use strict';

const api = {
    phaseDisplay(phase, config, rows, elapsedSeconds = 0) {
        const living = rows.find(row => Number(row.phase) === Number(phase.phase) && api.runtimeFresh(row, elapsedSeconds));
        return living ? {hp: Number(living.max_hp), damage: Number(living.damage), live: true}
            : {hp: config.hp ?? phase.hp, damage: config.damage ?? phase.damage, live: false};
    },
    runtimeFresh(row, elapsedSeconds = 0) {
        if (row.heartbeat_age === null || row.heartbeat_age === undefined) return false;
        const age = Number(row.heartbeat_age);
        return Number.isFinite(age) && age >= 0 && age + elapsedSeconds < 45;
    },
    probability(rules, index) {
        const rule = rules[index];
        if (!rule) return { conditional: 0, total: 0 };
        const sum = rule.group ? rules.filter(r => r.group === rule.group).reduce((n, r) => n + (r.weight || 1), 0) : (rule.weight || 1);
        if (sum <= 0) return { conditional: 0, total: 0 };
        const conditional = rule.group ? (rule.weight || 1) / sum * 100 : 100;
        return { conditional, total: (rule.chance_bp || 0) / 100 * conditional / 100 };
    },
    filter(entries, kind, q) {
        q = q.trim().toLocaleLowerCase();
        return entries.filter(e => {
            const matchesKind = (kind === 'all' ||
                (kind === 'quest' && e.quest) ||
                (kind === 'limited' && e.limited) ||
                (kind === 'general' && !e.quest && !e.limited));
            if (!matchesKind) return false;
            if (!q) return true;
            return [e.name, e.key, e.kind, e.class_name, String(e.boss_id || '')].join(' ').toLocaleLowerCase().includes(q);
        });
    },
    chance(value) {
        if (!/^\d{1,3}(?:\.\d{1,2})?$/.test(String(value)) || Number(value) > 100) {
            throw Error('Tỷ lệ phần trăm phải từ 0 đến 100%, tối đa 2 chữ số thập phân.');
        }
        return Math.round(Number(value) * 100);
    },
    options(text) {
        const seen = new Set();
        return text.trim() ? text.trim().split(/[\s,;]+/).map(s => {
            if (!/^\d+:\d+$/.test(s)) throw Error('Options phải dùng định dạng ID:param, ví dụ 50:20 30:0');
            const [id, param] = s.split(':').map(Number);
            if (id > 255 || param > 2147483647 || seen.has(id)) throw Error('Option bị trùng hoặc vượt quá giới hạn (ID max 255)');
            seen.add(id);
            return { id, param };
        }) : [];
    }
};

if (typeof module !== 'undefined') module.exports = api;
if (typeof document === 'undefined') return;

document.addEventListener('DOMContentLoaded', () => {
    const bootEl = document.getElementById('bossBoot');
    if (!bootEl) return;
    const boot = JSON.parse(bootEl.textContent);
    const el = id => document.getElementById(id);
    const form = el('bossForm');
    const field = name => form ? form.elements[name] : null;

    let page = 0;
    let selected = null;
    let dropNodes = [];
    let drafts = new Map();
    let activePhase = null;
    let statusReceivedAt = performance.now();

    const defaults = {
        hp: null,
        damage: null,
        cap_damage: null,
        cap_percent: null,
        defense: null,
        critical: null,
        apply_mode: 'alive',
        drop_mode: 'default',
        drops: [],
        revision: 0
    };

    const num = n => (n === null || n === undefined || n === '') ? 'Gốc' : Number(n).toLocaleString('vi-VN');
    const node = (tag, text) => {
        const n = document.createElement(tag);
        if (text !== undefined) n.textContent = text;
        return n;
    };
    const button = (text, fn) => {
        const b = node('button', text);
        b.type = 'button';
        b.className = 'btn btn-sm btn-outline';
        b.onclick = fn;
        return b;
    };
    const badge = (text, cls) => {
        const s = node('span', text);
        s.className = 'boss-badge ' + cls;
        return s;
    };
    const pill = (text, cls) => {
        const s = node('span', text);
        s.className = 'boss-pill ' + cls;
        return s;
    };

    const getAvatarUrl = bossKey => {
        const url = boot.avatars?.[bossKey]?.url;
        if (url) return url;
        return adminUrl('/favicon-32x32.png');
    };

    const isCustomConfig = c => {
        if (!c) return false;
        return (c.hp !== null && c.hp !== '') ||
               (c.damage !== null && c.damage !== '') ||
               (c.defense !== null && c.defense !== '') ||
               (c.critical !== null && c.critical !== '') ||
               (c.cap_damage !== null && c.cap_damage !== '') ||
               (c.cap_percent !== null && c.cap_percent !== '') ||
               (c.drop_mode && c.drop_mode !== 'default') ||
               (Array.isArray(c.drops) && c.drops.length > 0);
    };

    const labels = e => [
        e.quest ? 'Nhiệm vụ' : null,
        e.limited ? 'Giới hạn dame lên người' : null,
        !e.quest && !e.limited ? 'Bình thường' : null,
        e.kind
    ].filter(Boolean).join(' · ');

    const groupBadges = e => {
        const w = node('div');
        w.className = 'boss-badges';
        if (e.quest) w.append(badge('Nhiệm vụ', 'is-quest'));
        if (e.limited) w.append(badge('Giới hạn dame', 'is-limited'));
        if (!e.quest && !e.limited) w.append(badge('Bình thường', 'is-general'));
        if (e.kind) w.append(badge(e.kind, 'is-kind'));
        return w;
    };

    const phaseDefaultText = (e, p) => {
        return `HP gốc: ${e.dynamic_stats ? 'Động / cơ sở ' : ''}${num(p.hp)} | Sức đánh: ${num(p.damage)} | Map: ${(p.maps || []).join(', ') || 'Theo cơ chế gốc'}`;
    };

    const ackPill = c => {
        const cmd = boot.commands[c.revision];
        if (!c.revision) return pill('Mặc định', 'is-muted');
        if (!cmd) return pill('Chưa có ACK', 'is-muted');
        if (cmd.status === 'completed') return pill('Đã nạp', 'is-ok');
        if (cmd.status === 'failed') return pill('Lỗi nạp', 'is-err');
        return pill('Đang chờ ACK', 'is-wait');
    };

    function render() {
        const entries = api.filter(boot.catalog, el('bossFilter').value, el('bossSearch').value);
        const pages = Math.max(1, Math.ceil(entries.length / 20));
        page = Math.min(page, pages - 1);
        el('bossRows').replaceChildren();

        if (!entries.length) {
            const tr = node('tr');
            const td = node('td', 'Không tìm thấy boss nào phù hợp. Vui lòng thử từ khóa khác.');
            td.colSpan = 8;
            td.style.textAlign = 'center';
            td.style.padding = '36px';
            td.style.color = '#94a3b8';
            tr.append(td);
            el('bossRows').append(tr);
        } else {
            entries.slice(page * 20, page * 20 + 20).forEach(e => {
                const tr = node('tr');

                // Cột 1: Boss Cell với Avatar Resource
                const nameTd = node('td');
                const cell = node('div');
                cell.className = 'boss-cell';

                const avatarWrap = node('div');
                avatarWrap.className = 'boss-avatar-wrapper ' + (e.quest ? 'is-quest' : (e.limited ? 'is-limited' : 'is-general'));
                const img = node('img');
                img.src = getAvatarUrl(e.key);
                img.alt = e.name;
                img.onerror = () => { img.src = adminUrl('/favicon-32x32.png'); };
                avatarWrap.append(img);
                cell.append(avatarWrap);

                const meta = node('div');
                meta.className = 'boss-meta';
                const nm = node('div', e.name);
                nm.className = 'boss-name';
                meta.append(nm);

                const keyBadge = node('code', e.key);
                keyBadge.className = 'boss-key-badge';
                meta.append(keyBadge);
                meta.append(groupBadges(e));
                cell.append(meta);
                nameTd.append(cell);
                tr.append(nameTd);

                // Cột 2: Giai đoạn / Map
                const phaseTd = node('td');
                e.phases.forEach(p => {
                    const d = node('div', `${p.phase}: ${p.name} · Map ${(p.maps || []).join(', ') || 'Động'}`);
                    d.className = 'boss-line';
                    phaseTd.append(d);
                });
                tr.append(phaseTd);

                const configs = e.phases.map(p => ({ p, c: boot.configs[e.key]?.[p.phase] || defaults }));

                // Cột 3: HP & Sức đánh
                const statTd = node('td');
                configs.forEach(({ p, c }) => {
                    const d = node('div');
                    d.className = 'boss-line';
                    const custom = isCustomConfig(c);
                    const display = api.phaseDisplay(p, c, boot.runtime.filter(r => r.boss_key === e.key), (performance.now() - statusReceivedAt) / 1000);
                    const strong = node('span', `${p.phase}: HP ${num(display.hp)} / Dame ${num(display.damage)}`);
                    strong.className = 'boss-num';
                    d.append(strong);
                    d.append(pill(display.live ? 'Live' : custom ? 'Cấu hình' : 'Gốc', display.live ? 'is-ok' : 'is-muted'));
                    if (display.live) d.title = 'Chỉ số thực tế từ game; với nhiều boss cùng loại đây là mẫu một boss đang sống.';
                    statTd.append(d);
                    const baseLine = node('div', `Gốc ${p.phase}: HP ${num(p.hp)} / Dame ${num(p.damage)}`);
                    baseLine.className = 'boss-line boss-muted';
                    statTd.append(baseLine);
                    if (display.live && ((c.hp !== null && Number(c.hp) !== display.hp) || (c.damage !== null && Number(c.damage) !== display.damage))) {
                        const requested = node('div', `Đã lưu: HP ${num(c.hp ?? p.hp)} / Dame ${num(c.damage ?? p.damage)} · ${c.apply_mode === 'next' ? 'Chờ xuất hiện lại' : 'Chờ áp dụng'}`);
                        requested.className = 'boss-line boss-muted';statTd.append(requested);
                    }
                });
                tr.append(statTd);

                // Cột 4: Giáp & Chí mạng
                const defTd = node('td');
                configs.forEach(({ p, c }) => {
                    const d = node('div');
                    d.className = 'boss-line';
                    const hasDef = c.defense !== null && c.defense !== undefined;
                    const hasCrit = c.critical !== null && c.critical !== undefined;
                    d.textContent = `${p.phase}: Giáp ${hasDef ? num(c.defense) : '0'} / Crit ${hasCrit ? c.critical + '%' : '0%'}`;
                    if (!hasDef && !hasCrit) d.style.color = '#94a3b8';
                    defTd.append(d);
                });
                tr.append(defTd);

                // Cột 5: Giới hạn dame lên người
                const capTd = node('td');
                configs.forEach(({ p, c }) => {
                    const d = node('div');
                    d.className = 'boss-line' + ((c.cap_damage === null && c.cap_percent === null) ? ' boss-muted' : '');
                    d.textContent = `${p.phase}: ${c.cap_damage === null ? 'Gốc' : num(c.cap_damage)} / ${c.cap_percent === null ? 'Gốc' : c.cap_percent + '% HP'}`;
                    capTd.append(d);
                });
                tr.append(capTd);

                // Cột 6: Đang sống (Live Heartbeat)
                const liveTd = node('td');
                const elapsed = (performance.now() - statusReceivedAt) / 1000;
                const live = boot.runtime.filter(r => r.boss_key === e.key && api.runtimeFresh(r, elapsed));
                if (live.length) {
                    const statusLine = node('div');
                    statusLine.style.display = 'flex';
                    statusLine.style.alignItems = 'center';
                    statusLine.style.gap = '6px';
                    const dot = node('span');
                    dot.className = 'boss-pulse-dot';
                    statusLine.append(dot);
                    statusLine.append(node('span', `Sống (${live.reduce((n, r) => n + Number(r.alive_count || 0), 0)} con)`));
                    liveTd.append(statusLine);
                    live.forEach(r => {
                        const d = node('div', `${r.phase}: HP ${num(r.current_hp)}/${num(r.max_hp)}`);
                        d.className = 'boss-line';
                        d.style.fontSize = '12px';
                        liveTd.append(d);
                    });
                } else {
                    const d = node('div', 'Chưa có heartbeat');
                    d.className = 'boss-line boss-muted';
                    liveTd.append(d);
                }
                tr.append(liveTd);

                // Cột 7: Revision & ACK
                const statusTd = node('td');
                configs.forEach(({ p, c }) => {
                    const cmd = boot.commands[c.revision];
                    const d = node('div');
                    d.className = 'boss-line';
                    const rev = node('span', `${p.phase}: r${c.revision} `);
                    rev.className = 'boss-num';
                    d.append(rev);
                    const st = ackPill(c);
                    d.append(st);
                    statusTd.append(d);
                    if (cmd && !['completed', 'failed'].includes(cmd.status)) {
                        poll(cmd, st, p.phase, c.revision);
                    }
                });
                tr.append(statusTd);

                // Cột 8: Thao tác
                const actTd = node('td');
                actTd.style.textAlign = 'center';
                const edit = button('Sửa', () => open(e));
                edit.className = 'btn-boss-edit';
                actTd.append(edit);
                tr.append(actTd);

                el('bossRows').append(tr);
            });
        }

        el('bossPage').textContent = `Hiển thị ${entries.length} boss · Trang ${page + 1}/${pages}`;
        el('bossPrev').disabled = page === 0;
        el('bossNext').disabled = page === pages - 1;
    }

    const polling = new Map();
    function poll(cmd, line, phase, revision) {
        if (!cmd || !cmd.id) return;
        let promise = polling.get(cmd.id);
        if (!promise) {
            promise = waitForAdminCommand(cmd.id).then(r => {
                if (r.status === 'success') cmd.status = 'completed';
                else if (r.status === 'error') {
                    cmd.status = 'failed';
                    cmd.result_message = r.message;
                }
                return r;
            });
            polling.set(cmd.id, promise);
        }
        promise.then(r => {
            const ok = r.status === 'success', err = r.status === 'error';
            line.textContent = ok ? 'Đã nạp' : err ? 'Lỗi' : 'Đang chờ ACK';
            line.className = 'boss-pill ' + (ok ? 'is-ok' : err ? 'is-err' : 'is-wait');
            if (err) line.title = r.message;
        }).catch(() => {
            line.textContent = 'Chưa xác nhận ACK';
            line.className = 'boss-pill is-muted';
        });
    }

    function panel(name) {
        document.querySelectorAll('[data-boss-panel]').forEach(n => {
            n.hidden = n.dataset.bossPanel !== name;
        });
        document.querySelectorAll('.boss-tab').forEach(n => {
            n.setAttribute('aria-selected', String(n.dataset.panel === name));
        });
    }

    function collectDrops() {
        return dropNodes.map(({ inputs }, index) => {
            const rawId = inputs.item_id.value.trim();
            if (rawId === '' || isNaN(Number(rawId)) || Number(rawId) < 0 || Number(rawId) > 32767) {
                throw Error(`Quy tắc #${index + 1}: Vui lòng nhập Item ID hợp lệ (0 - 32767).`);
            }
            const qMin = Number(inputs.quantity_min.value);
            const qMax = Number(inputs.quantity_max.value);
            if (isNaN(qMin) || qMin < 1) {
                throw Error(`Quy tắc #${index + 1}: Số lượng min phải từ 1 trở lên.`);
            }
            if (isNaN(qMax) || qMax < qMin) {
                throw Error(`Quy tắc #${index + 1}: Số lượng max (${qMax}) không được nhỏ hơn min (${qMin}).`);
            }
            const weight = Number(inputs.weight.value);
            if (isNaN(weight) || weight < 1) {
                throw Error(`Quy tắc #${index + 1}: Trọng số trong nhóm phải từ 1 trở lên.`);
            }
            return {
                group: inputs.group.value.trim(),
                item_id: Number(rawId),
                quantity_min: qMin,
                quantity_max: qMax,
                chance_bp: api.chance(inputs.chance.value),
                weight: weight,
                options: api.options(inputs.options.value)
            };
        });
    }

    function snapshot() {
        if (activePhase !== null) {
            drafts.set(activePhase, Object.fromEntries(
                ['hp', 'damage', 'cap_damage', 'cap_percent', 'defense', 'critical', 'apply_mode', 'drop_mode', 'expected_revision']
                .map(k => [k, field(k).value])
                .concat([['drops', collectDrops()]])
            ));
        }
    }

    function loadPhase() {
        activePhase = field('phase').value;
        const currentPhaseObj = selected.phases.find(p => String(p.phase) === activePhase) || selected.phases[0] || {};
        el('bossModalAvatar').src = boot.avatars?.[selected.key]?.phases?.[activePhase]?.url || getAvatarUrl(selected.key);
        const c = drafts.get(activePhase) || boot.configs[selected.key]?.[activePhase] || defaults;

        for (const k of ['hp', 'damage', 'cap_damage', 'cap_percent', 'defense', 'critical', 'apply_mode', 'drop_mode']) {
            field(k).value = c[k] ?? '';
        }
        // Each new edit applies now unless the admin explicitly chooses next spawn.
        field('apply_mode').value = drafts.get(activePhase)?.apply_mode ?? 'alive';
        field('expected_revision').value = c.expected_revision ?? c.revision ?? 0;

        // Cập nhật thông số đối chiếu gốc
        el('refBaseHp').textContent = num(currentPhaseObj.hp);
        el('refBaseDame').textContent = num(currentPhaseObj.damage);
        el('refBaseMaps').textContent = (currentPhaseObj.maps || []).join(', ') || 'Động';

        // Gợi ý placeholder là chính con số gốc để admin dễ so sánh
        if (el('inputHp')) el('inputHp').placeholder = `Gốc: ${num(currentPhaseObj.hp)}`;
        if (el('inputDamage')) el('inputDamage').placeholder = `Gốc: ${num(currentPhaseObj.damage)}`;

        el('bossDefault').textContent = phaseDefaultText(selected, currentPhaseObj);
        el('bossPhaseBadge').textContent = `Phase ${currentPhaseObj.phase}: ${currentPhaseObj.name}`;

        dropNodes = [];
        el('bossDropRows').replaceChildren();
        (c.drops || []).forEach(addDrop);

        el('bossHistory').replaceChildren();
        boot.audit.filter(a => a.boss_key === selected.key && String(a.phase) === activePhase).forEach(a => {
            const o = node('option', `r${a.revision} · ${a.created_at} · Admin #${a.actor_id}`);
            o.value = a.id;
            el('bossHistory').append(o);
        });
        el('bossRollback').disabled = !boot.available || !el('bossHistory').options.length;
    }

    function setupQuickTools() {
        document.querySelectorAll('.btn-quick').forEach(btn => {
            btn.onclick = () => {
                const target = btn.dataset.target;
                const action = btn.dataset.action;
                const fixedVal = btn.dataset.val;
                const input = field(target);
                if (!input) return;

                const curPhaseObj = selected?.phases.find(p => String(p.phase) === activePhase) || {};
                const baseVal = Number(target === 'hp' ? curPhaseObj.hp : curPhaseObj.damage) || 0;

                if (fixedVal !== undefined) {
                    input.value = fixedVal;
                } else if (action === 'base') {
                    input.value = baseVal > 0 ? baseVal : '';
                } else if (action === 'add20') {
                    input.value = Math.round(baseVal * 1.2);
                } else if (action === 'add50') {
                    input.value = Math.round(baseVal * 1.5);
                } else if (action === 'mul2') {
                    input.value = Math.round(baseVal * 2);
                } else if (action === 'mul5') {
                    input.value = Math.round(baseVal * 5);
                } else if (action === 'clear') {
                    input.value = '';
                }
            };
        });
    }

    function open(e) {
        selected = e;
        drafts = new Map();
        activePhase = null;

        field('boss_key').value = e.key;
        field('request_id').value = adminRequestId();
        field('action').value = 'save';

        // Avatar & Header Modal
        const avatarEl = el('bossModalAvatar');
        if (avatarEl) {
            avatarEl.src = getAvatarUrl(e.key);
            avatarEl.onerror = () => { avatarEl.src = adminUrl('/favicon-32x32.png'); };
        }
        el('bossTitle').textContent = e.name;
        el('bossKey').textContent = `${e.key} · ${labels(e)} · ${e.class_name}`;
        el('bossInfo').textContent = `${e.class_name} (${e.kind})`;
        el('bossDropSummary').textContent = e.drop_summary;
        el('bossSource').textContent = e.source || 'SRC/src/nro/models/boss/...';

        // Alert box
        const alertBox = el('bossModalAlert');
        if (alertBox) { alertBox.style.display = 'none'; alertBox.textContent = ''; }

        // Danh sách phases
        el('bossPhase').replaceChildren();
        e.phases.forEach(p => {
            const o = node('option', `Giai đoạn ${p.phase}: ${p.name}`);
            o.value = p.phase;
            el('bossPhase').append(o);
        });

        loadPhase();
        panel('stats'); // Mở trực tiếp tab Chỉ số theo yêu cầu người dùng
        el('bossModal').showModal();
    }

    function addDrop(rule = { group: '', item_id: '', quantity_min: 1, quantity_max: 1, chance_bp: 10000, weight: 1, options: [] }) {
        const row = node('div');
        row.className = 'boss-drop';

        const title = node('div');
        title.className = 'boss-card-title';
        row.append(title);

        const grid = node('div');
        grid.className = 'boss-grid';
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(130px, 1fr))';
        grid.style.gap = '10px';

        const inputs = {};
        for (const [key, labelText, value, type] of [
            ['item_id', 'Item ID', rule.item_id, 'number'],
            ['quantity_min', 'Số lượng Min', rule.quantity_min, 'number'],
            ['quantity_max', 'Số lượng Max', rule.quantity_max, 'number'],
            ['chance', 'Tỷ lệ rơi %', rule.chance_bp / 100, 'number'],
            ['group', 'Tên nhóm (trống = độc lập)', rule.group, 'text'],
            ['weight', 'Trọng số nhóm', rule.weight, 'number'],
            ['options', 'Options ID:param', rule.options.map(o => `${o.id}:${o.param}`).join(' '), 'text']
        ]) {
            const labelNode = node('label', labelText);
            labelNode.style.fontSize = '12px';
            labelNode.style.fontWeight = '600';
            labelNode.style.display = 'flex';
            labelNode.style.flexDirection = 'column';
            labelNode.style.gap = '4px';

            const input = node('input');
            input.className = 'form-control';
            input.style.background = '#0f172a';
            input.style.color = '#fff';
            input.style.borderColor = 'var(--boss-border)';
            input.value = value;
            input.type = type;
            if (type === 'number') {
                input.min = key === 'item_id' || key === 'chance' ? 0 : 1;
                input.max = key === 'chance' ? 100 : key === 'item_id' ? 32767 : 2147483647;
                if (key === 'chance') input.step = '0.01';
            }
            inputs[key] = input;
            labelNode.append(input);
            grid.append(labelNode);
        }

        const search = node('input');
        search.className = 'form-control';
        search.style.background = '#0f172a';
        search.style.color = '#fff';
        search.style.marginBottom = '8px';
        search.placeholder = '🔍 Tìm kiếm tên hoặc ID vật phẩm...';
        const results = node('div');
        results.className = 'boss-results';

        let generation = 0, timer;
        search.oninput = () => {
            clearTimeout(timer);
            const g = ++generation;
            results.replaceChildren();
            timer = setTimeout(async () => {
                try {
                    const response = await fetch(`${adminUrl('')}/admin/api/search_items.php?action=items&q=${encodeURIComponent(search.value)}&limit=20`, {
                        headers: { 'X-CSRF-Token': adminCsrfToken() }
                    });
                    const data = await response.json();
                    if (!response.ok || data.status !== 'success') throw Error(data.message || 'Không thể tìm vật phẩm');
                    if (g !== generation) return;
                    data.data.forEach(item => {
                        const b = button(`#${item.id} ${item.name}`, () => {
                            inputs.item_id.value = item.id;
                            search.value = item.name;
                            results.replaceChildren();
                        });
                        b.style.margin = '2px';
                        if (item.icon_url) {
                            const iconImg = node('img');
                            iconImg.src = item.icon_url;
                            iconImg.alt = '';
                            iconImg.width = 20;
                            iconImg.height = 20;
                            iconImg.style.verticalAlign = 'middle';
                            iconImg.style.marginRight = '4px';
                            b.prepend(iconImg);
                        }
                        results.append(b);
                    });
                } catch (e) {
                    const alertBox = el('bossModalAlert');
                    if (alertBox) { alertBox.textContent = e.message; alertBox.style.display = 'block'; }
                }
            }, 250);
        };

        const optionSearch = node('input');
        optionSearch.className = 'form-control';
        optionSearch.style.background = '#0f172a';
        optionSearch.style.color = '#fff';
        optionSearch.style.marginTop = '8px';
        optionSearch.placeholder = '🔍 Tìm Option vật phẩm theo tên / ID...';
        const optionResults = node('div');
        optionResults.className = 'boss-results';

        let optionGeneration = 0, optionTimer;
        optionSearch.oninput = () => {
            clearTimeout(optionTimer);
            const g = ++optionGeneration;
            optionResults.replaceChildren();
            optionTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`${adminUrl('')}/admin/api/search_items.php?action=options&q=${encodeURIComponent(optionSearch.value)}&limit=20`, {
                        headers: { 'X-CSRF-Token': adminCsrfToken() }
                    });
                    const data = await res.json();
                    if (!res.ok || data.status !== 'success') throw Error(data.message || 'Không thể tìm option');
                    if (g !== optionGeneration) return;
                    data.data.forEach(op => {
                        const b = button(`#${op.id}: ${op.name}`, () => {
                            try {
                                const ops = api.options(inputs.options.value);
                                if (!ops.some(o => o.id === Number(op.id))) {
                                    inputs.options.value += (inputs.options.value ? ' ' : '') + op.id + ':0';
                                }
                            } catch (_) {
                                inputs.options.value += (inputs.options.value ? ' ' : '') + op.id + ':0';
                            }
                            optionResults.replaceChildren();
                        });
                        b.style.margin = '2px';
                        optionResults.append(b);
                    });
                } catch (e) {
                    const alertBox = el('bossModalAlert');
                    if (alertBox) { alertBox.textContent = e.message; alertBox.style.display = 'block'; }
                }
            }, 250);
        };

        const preview = node('p');
        preview.className = 'boss-odds';
        preview.style.color = '#38bdf8';
        preview.style.fontWeight = '600';
        preview.style.fontSize = '12.5px';
        preview.style.margin = '8px 0';

        const record = { row, inputs, preview };
        dropNodes.push(record);

        const deleteBtn = button('✕ Xóa quy tắc này', () => {
            dropNodes = dropNodes.filter(n => n !== record);
            row.remove();
            previewOdds();
        });
        deleteBtn.className = 'btn btn-sm btn-outline-danger';
        deleteBtn.style.marginTop = '8px';

        row.append(search, results, grid, preview, optionSearch, optionResults, deleteBtn);
        el('bossDropRows').append(row);
        row.addEventListener('input', previewOdds);
        previewOdds();
    }

    function previewOdds() {
        try {
            const rules = collectDrops();
            dropNodes.forEach((r, i) => {
                r.row.firstChild.textContent = `Quy tắc #${i + 1} ${rules[i].group ? `· Nhóm: ${rules[i].group}` : '· Roll độc lập'}`;
                const odds = api.probability(rules, i);
                r.preview.textContent = rules[i].group
                    ? `Khi nhóm kích hoạt: ${odds.conditional.toFixed(2)}% trúng món này | Xác suất tổng: ${odds.total.toFixed(4)}%.`
                    : `Roll độc lập: ${odds.total.toFixed(2)}% xác suất ra món.`;
            });
        } catch (_) {
            dropNodes.forEach(r => {
                r.preview.textContent = 'Điền Item ID, tỷ lệ và Options hợp lệ để xem xác suất.';
            });
        }
    }

    // Sự kiện Filter & Search
    el('bossSearch').oninput = el('bossFilter').onchange = () => {
        page = 0;
        render();
    };
    el('bossPrev').onclick = () => { page--; render(); };
    el('bossNext').onclick = () => { page++; render(); };

    // Đóng Modal
    el('bossClose').onclick = () => el('bossModal').close();

    // Chuyển Tab
    document.querySelectorAll('.boss-tab').forEach(n => {
        n.onclick = () => panel(n.dataset.panel);
    });

    // Đổi Giai đoạn
    el('bossPhase').onchange = () => {
        try {
            snapshot();
            loadPhase();
            const alertBox = el('bossModalAlert');
            if (alertBox) alertBox.style.display = 'none';
        } catch (e) {
            field('phase').value = activePhase;
            const alertBox = el('bossModalAlert');
            if (alertBox) { alertBox.textContent = e.message; alertBox.style.display = 'block'; }
        }
    };

    el('bossAddDrop').onclick = () => addDrop();

    // Submit Form
    form.onsubmit = e => {
        try {
            field('drops_json').value = JSON.stringify(collectDrops());
            field('action').value = 'save';
            field('request_id').value = adminRequestId();
        } catch (err) {
            e.preventDefault();
            const alertBox = el('bossModalAlert');
            if (alertBox) {
                alertBox.textContent = err.message;
                alertBox.style.display = 'block';
            }
            panel('drops');
        }
    };

    function action(kind) {
        field('action').value = kind;
        field('drops_json').value = '[]';
        field('request_id').value = adminRequestId();
        form.submit();
    }

    el('bossReset').onclick = () => {
        if (confirm('Đặt lại toàn bộ chỉ số và rơi đồ của giai đoạn này về mặc định?')) {
            action('reset');
        }
    };

    el('bossRollback').onclick = () => {
        if (el('bossHistory').value && confirm('Khôi phục cấu hình trước lần thay đổi này và tạo revision mới?')) {
            action('rollback');
        }
    };

    setupQuickTools();
    render();

    async function refreshStatus() {
        try {
            if (document.hidden) return;
            const response = await fetch(adminUrl('/admin/api/boss_status.php'), {
                cache: 'no-store', signal: AbortSignal.timeout(10000)
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') throw Error(result.message || 'Không thể đọc trạng thái boss');
            boot.runtime = result.data.runtime;
            boot.commands = result.data.commands;
            if (result.data.configs) boot.configs = result.data.configs;
            boot.live = result.data.live;
            statusReceivedAt = performance.now();
            const banner = el('bossLiveStatus');
            banner.className = 'boss-alert-box ' + (boot.live.fresh ? 'is-success' : 'is-error');
            banner.textContent = boot.live.fresh
                ? 'Server đang gửi heartbeat boss. Trạng thái Live và ACK tự cập nhật mỗi 5 giây.'
                : 'Chưa có heartbeat boss mới. Lệnh đã lưu sẽ chờ server xử lý.';
            if (!boot.live.fresh && boot.live.last_heartbeat) banner.textContent += ' Heartbeat cuối: ' + boot.live.last_heartbeat + '.';
            render();
        } catch (error) {
            const banner = el('bossLiveStatus');
            banner.className = 'boss-alert-box is-error';
            banner.textContent = 'Không thể cập nhật trạng thái boss. Cấu hình đã lưu vẫn được giữ nguyên.';
            render();
        } finally {
            setTimeout(refreshStatus, 5000);
        }
    }
    refreshStatus();

    // Phục hồi trạng thái sau lỗi submit
    if (boot.restore) {
        const p = boot.restore;
        const e = boot.catalog.find(cat => cat.key === p.boss_key);
        if (e) {
            open(e);
            field('phase').value = p.phase;
            loadPhase();
            for (const k of ['hp', 'damage', 'cap_damage', 'cap_percent', 'defense', 'critical', 'apply_mode', 'drop_mode', 'expected_revision']) {
                if (typeof p[k] === 'string') field(k).value = p[k];
            }
            try {
                dropNodes = [];
                el('bossDropRows').replaceChildren();
                JSON.parse(p.drops_json || '[]').forEach(addDrop);
            } catch (_) {}

            const alertBox = el('bossModalAlert');
            if (alertBox) {
                alertBox.textContent = boot.message || 'Lưu thất bại. Vui lòng kiểm tra lại thông số nhập vào.';
                alertBox.style.display = 'block';
            }
        }
    }
});
})(typeof globalThis === 'undefined' ? this : globalThis);
