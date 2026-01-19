<div class="content">
    <div class="page-action">
        <form id="lead-filter-form" method="GET" action="{{ route('admin.leads.index') }}" style="display:inline-block;margin-right:10px; position:relative;">
            <input type="text" name="organization_search" value="" placeholder="Buscar organización" autocomplete="off" />
            <div id="org-selected" style="display:inline-block; margin-left:10px;"></div>
            <ul id="org-suggestions" style="position:absolute; background:#1e1e1e; border:1px solid #444; list-style:none; margin:0; padding:0; max-height:200px; overflow:auto; width:300px; display:none;"></ul>
            <div id="org-hidden-filter"></div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('admin.leads.index') }}" class="btn">Limpiar</a>
        </form>
        <form id="lead-export-form" method="GET" action="{{ route('admin.leads.export') }}" style="display:inline-block;">
            <div id="org-hidden-export"></div>
            <button type="submit" class="btn btn-primary">Exportar CSV</button>
        </form>
    </div>
    @php $qs = http_build_query(['organization_ids' => request('organization_ids')]); @endphp
    <table-component
        src="{{ route('admin.leads.grid') }}{{ $qs ? ('?'.$qs) : '' }}"
        :columns="[
            { name: 'id', label: 'ID' },
            { name: 'title', label: 'Título' },
            { name: 'organization_name', label: 'Organización' },
            { name: 'created_at', label: 'Creado' }
        ]"
    ></table-component>
    <script>
    const input = document.querySelector('input[name="organization_search"]');
    const list = document.getElementById('org-suggestions');
    const selectedWrap = document.getElementById('org-selected');
    const hiddenFilter = document.getElementById('org-hidden-filter');
    const hiddenExport = document.getElementById('org-hidden-export');
    let ctrl;
    const selected = [];

    function renderHidden() {
      hiddenFilter.innerHTML = '';
      hiddenExport.innerHTML = '';
      selectedWrap.innerHTML = '';
      selected.forEach(item => {
        const hf = document.createElement('input');
        hf.type = 'hidden';
        hf.name = 'organization_ids[]';
        hf.value = item.id;
        hiddenFilter.appendChild(hf);

        const he = document.createElement('input');
        he.type = 'hidden';
        he.name = 'organization_ids[]';
        he.value = item.id;
        hiddenExport.appendChild(he);

        const chip = document.createElement('span');
        chip.textContent = item.name;
        chip.style.display = 'inline-block';
        chip.style.padding = '4px 8px';
        chip.style.margin = '0 6px';
        chip.style.border = '1px solid #444';
        chip.style.borderRadius = '12px';
        selectedWrap.appendChild(chip);
      });
    }

    input && input.addEventListener('input', async (e) => {
      const q = e.target.value.trim();
      if (ctrl) { ctrl.abort(); }
      if (!q) { list.style.display = 'none'; list.innerHTML = ''; return; }
      ctrl = new AbortController();
      try {
        const res = await fetch('{{ route('admin.organizations.search') }}?q=' + encodeURIComponent(q), { signal: ctrl.signal });
        const data = await res.json();
        list.innerHTML = '';
        data.forEach(item => {
          const li = document.createElement('li');
          li.textContent = item.name;
          li.style.padding = '6px 8px';
          li.style.cursor = 'pointer';
          li.addEventListener('click', () => {
            if (!selected.find(s => String(s.id) === String(item.id))) {
              selected.push(item);
              renderHidden();
            }
            input.value = '';
            list.style.display = 'none';
            list.innerHTML = '';
          });
          list.appendChild(li);
        });
        list.style.display = data.length ? 'block' : 'none';
      } catch (err) {
        list.style.display = 'none';
        list.innerHTML = '';
      }
    });

    document.addEventListener('click', (e) => {
      if (!list.contains(e.target) && e.target !== input) {
        list.style.display = 'none';
      }
    });
    </script>
</div>
