@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@section('content')
<div class="card">
    <div class="card-title">
        <span class="icon">👥</span>
        Gestión de Usuarios
        <button class="btn btn-primary btn-sm" style="margin-left: auto;" onclick="openModal()">
            ➕ Nuevo Usuario
        </button>
    </div>

    <div class="results-table-wrapper">
        <table class="results-table" id="usersTable">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr id="user-row-{{ $user->id }}">
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="badge-role {{ $user->role }}">{{ $user->role }}</span>
                    </td>
                    <td>{{ $user->created_at->format('d/m/Y') }}</td>
                    <td>
                        <button class="btn btn-outline btn-xs" onclick="editUser({{ $user->id }}, '{{ $user->name }}', '{{ $user->email }}', '{{ $user->role }}')">✏️</button>
                        @if($user->id !== auth()->id())
                        <button class="btn btn-danger btn-xs" onclick="deleteUser({{ $user->id }})">🗑️</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="userModal">
    <div class="modal">
        <h3 id="modalTitle">Nuevo Usuario</h3>
        <form id="userForm" onsubmit="saveUser(event)">
            <input type="hidden" id="userId" value="">
            <div class="form-group">
                <label for="userName">Nombre</label>
                <input type="text" id="userName" required placeholder="Nombre completo">
            </div>
            <div class="form-group">
                <label for="userEmail">Email</label>
                <input type="email" id="userEmail" required placeholder="correo@ejemplo.com">
            </div>
            <div class="form-group">
                <label for="userPassword">Contraseña <span id="pwdHint" style="font-size:0.65rem; text-transform:none; color:var(--text-muted);"></span></label>
                <input type="password" id="userPassword" placeholder="Mínimo 6 caracteres">
            </div>
            <div class="form-group">
                <label for="userRole">Rol</label>
                <select id="userRole" required>
                    <option value="admin">Admin</option>
                    <option value="consulta">Consulta</option>
                </select>
            </div>
            <div class="btn-group" style="justify-content: flex-end;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">💾 Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openModal(isEdit = false) {
        document.getElementById('userModal').classList.add('active');
        if (!isEdit) {
            document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
            document.getElementById('userId').value = '';
            document.getElementById('userName').value = '';
            document.getElementById('userEmail').value = '';
            document.getElementById('userPassword').value = '';
            document.getElementById('userPassword').required = true;
            document.getElementById('userRole').value = 'consulta';
            document.getElementById('pwdHint').textContent = '';
        }
    }

    function closeModal() {
        document.getElementById('userModal').classList.remove('active');
    }

    function editUser(id, name, email, role) {
        openModal(true);
        document.getElementById('modalTitle').textContent = 'Editar Usuario';
        document.getElementById('userId').value = id;
        document.getElementById('userName').value = name;
        document.getElementById('userEmail').value = email;
        document.getElementById('userPassword').value = '';
        document.getElementById('userPassword').required = false;
        document.getElementById('userRole').value = role;
        document.getElementById('pwdHint').textContent = '(dejar vacío para no cambiar)';
    }

    async function saveUser(e) {
        e.preventDefault();
        const id = document.getElementById('userId').value;
        const isEdit = !!id;

        const body = {
            name: document.getElementById('userName').value,
            email: document.getElementById('userEmail').value,
            role: document.getElementById('userRole').value,
        };

        const pwd = document.getElementById('userPassword').value;
        if (pwd) body.password = pwd;

        if (!isEdit && !pwd) {
            showAlert('error', 'La contraseña es obligatoria para nuevos usuarios.');
            return;
        }

        try {
            const url = isEdit ? `/usuarios/${id}` : '/usuarios';
            const method = isEdit ? 'PUT' : 'POST';

            const response = await fetchApi(url, {
                method,
                body: JSON.stringify(body),
            });

            const data = await response.json();

            if (data.success) {
                showAlert('success', data.message);
                closeModal();
                location.reload();
            } else {
                showAlert('error', data.message || 'Error al guardar.');
            }
        } catch (error) {
            showAlert('error', 'Error: ' + error.message);
        }
    }

    async function deleteUser(id) {
        if (!confirm('¿Está seguro de eliminar este usuario?')) return;

        try {
            const response = await fetchApi(`/usuarios/${id}`, { method: 'DELETE' });
            const data = await response.json();

            if (data.success) {
                document.getElementById('user-row-' + id)?.remove();
                showAlert('success', data.message);
            } else {
                showAlert('error', data.message);
            }
        } catch (error) {
            showAlert('error', 'Error: ' + error.message);
        }
    }

    // Close modal on overlay click
    document.getElementById('userModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
</script>
@endsection
