(function () {
    var input = document.querySelector('input[data-suggest]');
    if (!input) { return; }
    var endpoint = input.getAttribute('data-suggest');
    var list = document.getElementById('suggest-list');
    var timer = null;
    var seq = 0;
    var active = -1;
    var items = [];

    if (window.location.hash === '#q') { input.focus(); }

    function close() {
        list.hidden = true;
        list.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
        items = [];
    }

    function setActive(i) {
        items.forEach(function (li, n) {
            li.classList.toggle('is-active', n === i);
            li.setAttribute('aria-selected', n === i ? 'true' : 'false');
        });
        active = i;
        if (i >= 0 && items[i]) { input.setAttribute('aria-activedescendant', items[i].id); }
        else { input.removeAttribute('aria-activedescendant'); }
    }

    function render(data) {
        list.innerHTML = '';
        items = [];
        var n = 0;
        function add(href, html, cls) {
            var li = document.createElement('li');
            li.id = 'suggest-' + n++;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');
            li.className = cls || '';
            var a = document.createElement('a');
            a.href = href;
            a.tabIndex = -1;
            a.appendChild(html);
            li.appendChild(a);
            list.appendChild(li);
            items.push(li);
        }
        (data.products || []).forEach(function (p) {
            var wrap = document.createElement('span');
            wrap.className = 'suggest-item';
            var img = document.createElement('img');
            img.src = p.image;
            img.alt = '';
            img.width = 40;
            img.height = 40;
            var txt = document.createElement('span');
            var name = document.createElement('strong');
            name.textContent = p.name;
            var meta = document.createElement('span');
            meta.className = 'muted';
            meta.textContent = p.category + ' · ' + p.price;
            txt.appendChild(name);
            txt.appendChild(document.createElement('br'));
            txt.appendChild(meta);
            wrap.appendChild(img);
            wrap.appendChild(txt);
            add(p.url, wrap);
        });
        (data.categories || []).forEach(function (c) {
            var s = document.createElement('span');
            s.className = 'suggest-cat';
            s.textContent = 'All ' + c.name.toLowerCase();
            add(c.url, s, 'suggest-category');
        });
        if (!items.length) { close(); return; }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function fetchSuggestions() {
        var q = input.value.trim();
        if (q.length < 2) { close(); return; }
        var mine = ++seq;
        fetch(endpoint + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (data && mine === seq) { render(data); } })
            .catch(function () { close(); });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(fetchSuggestions, 160);
    });

    input.addEventListener('keydown', function (e) {
        if (list.hidden) { return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((active + 1) % items.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active <= 0 ? items.length - 1 : active - 1); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); window.location.href = items[active].querySelector('a').href; }
        else if (e.key === 'Escape') { close(); }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('.search-box')) { close(); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
})();
