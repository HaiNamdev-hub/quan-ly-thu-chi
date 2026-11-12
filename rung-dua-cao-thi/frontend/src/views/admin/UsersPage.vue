<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Search, UserPlus, ShieldCheck, UserRound, CircleCheck, CircleOff, KeyRound, X, LoaderCircle, Pencil, History } from 'lucide-vue-next'
import { api } from '../../services/api'
import type { UserRole } from '../../types/auth'

interface ManagedUser {
  id: number; code: string; name: string; username: string | null; email: string; phone: string | null
  role: UserRole; is_active: boolean; created_at: string; last_login_at: string | null
}
interface PaginatedUsers { data: ManagedUser[]; current_page: number; last_page: number; total: number }

const users = ref<ManagedUser[]>([])
const search = ref('')
const roleFilter = ref('')
const activeFilter = ref('')
const loading = ref(false)
const errorMessage = ref('')
const isCreateOpen = ref(false)
const isResetOpen = ref(false)
const isActivityOpen = ref(false)
const editingUserId = ref<number | null>(null)
const resetTarget = ref<ManagedUser | null>(null)
const activityTarget = ref<ManagedUser | null>(null)
const activityRows = ref<Array<{ action: string; before: unknown; after: unknown; created_at: string }>>([])
const resetPassword = ref('')
const saving = ref(false)
const form = ref({ name: '', username: '', email: '', phone: '', role: 'STAFF' as UserRole, password: '' })
const visibleUsers = computed(() => users.value)

async function loadUsers(): Promise<void> {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await api.get<PaginatedUsers>('/api/admin/users', { params: { search: search.value || undefined, role: roleFilter.value || undefined, active: activeFilter.value || undefined } })
    users.value = response.data.data
  } catch {
    errorMessage.value = 'Không tải được danh sách tài khoản.'
  } finally {
    loading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout>
watch([search, roleFilter, activeFilter], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadUsers, 250)
})
onMounted(loadUsers)

async function createUser(): Promise<void> {
  saving.value = true
  errorMessage.value = ''
  try {
    await api.post('/api/admin/users', form.value)
    isCreateOpen.value = false
    form.value = { name: '', username: '', email: '', phone: '', role: 'STAFF', password: '' }
    await loadUsers()
  } catch {
    errorMessage.value = 'Chưa thể tạo tài khoản. Hãy kiểm tra username, email và mật khẩu (tối thiểu 12 ký tự).'
  } finally {
    saving.value = false
  }
}

function openCreate(): void {
  editingUserId.value = null
  form.value = { name: '', username: '', email: '', phone: '', role: 'STAFF', password: '' }
  isCreateOpen.value = true
}

function openEdit(user: ManagedUser): void {
  editingUserId.value = user.id
  form.value = { name: user.name, username: user.username ?? '', email: user.email, phone: user.phone ?? '', role: user.role, password: '' }
  isCreateOpen.value = true
}

async function saveUser(): Promise<void> {
  if (editingUserId.value === null) {
    await createUser()
    return
  }
  saving.value = true
  errorMessage.value = ''
  try {
    const { password: _password, ...profile } = form.value
    await api.put(`/api/admin/users/${editingUserId.value}`, profile)
    isCreateOpen.value = false
    await loadUsers()
  } catch {
    errorMessage.value = 'Chưa thể cập nhật tài khoản. Vui lòng kiểm tra username và email.'
  } finally {
    saving.value = false
  }
}

async function submitPasswordReset(): Promise<void> {
  if (!resetTarget.value) return
  saving.value = true
  try {
    await api.post(`/api/admin/users/${resetTarget.value.id}/reset-password`, { password: resetPassword.value })
    isResetOpen.value = false
    resetPassword.value = ''
  } catch {
    errorMessage.value = 'Không thể đặt lại mật khẩu. Mật khẩu cần có ít nhất 12 ký tự.'
  } finally {
    saving.value = false
  }
}

async function showActivity(user: ManagedUser): Promise<void> {
  activityTarget.value = user
  isActivityOpen.value = true
  try {
    const response = await api.get<{ activity: typeof activityRows.value }>(`/api/admin/users/${user.id}/activity`)
    activityRows.value = response.data.activity
  } catch {
    errorMessage.value = 'Không tải được lịch sử hoạt động.'
  }
}

async function toggleActive(user: ManagedUser): Promise<void> {
  const confirmed = window.confirm(`${user.is_active ? 'Khóa' : 'Mở khóa'} tài khoản ${user.name}?`)
  if (!confirmed) return
  try {
    await api.put(`/api/admin/users/${user.id}`, { is_active: !user.is_active })
    await loadUsers()
  } catch {
    errorMessage.value = 'Không thể cập nhật trạng thái tài khoản.'
  }
}

function roleLabel(role: UserRole): string {
  return role === 'ADMIN' ? 'Quản trị viên' : role === 'MANAGER' ? 'Quản lý' : 'Nhân viên'
}
function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleString('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }) : 'Chưa đăng nhập'
}
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Quản trị hệ thống</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Tài khoản nhân sự</h1><p class="mt-1.5 text-sm text-slate-500">Tạo tài khoản và quản lý quyền truy cập hệ thống.</p></div><button class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#103a30]" @click="openCreate"><UserPlus :size="16" />Tạo tài khoản</button></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm shadow-slate-900/[0.02]">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center">
        <div class="relative min-w-0 flex-1"><Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" :size="16" /><input v-model="search" class="h-10 w-full rounded-xl border border-slate-200 pl-9 pr-3 text-sm outline-none transition focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/10" placeholder="Tìm tên, username hoặc email" /></div>
        <select v-model="roleFilter" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-600 outline-none focus:border-emerald-700"><option value="">Tất cả vai trò</option><option value="ADMIN">Quản trị viên</option><option value="MANAGER">Quản lý</option><option value="STAFF">Nhân viên</option></select>
        <select v-model="activeFilter" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-600 outline-none focus:border-emerald-700"><option value="">Tất cả trạng thái</option><option value="true">Đang hoạt động</option><option value="false">Đã khóa</option></select>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
          <thead class="bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3.5">Người dùng</th><th class="px-4 py-3.5">Username / Email</th><th class="px-4 py-3.5">Vai trò</th><th class="px-4 py-3.5">Trạng thái</th><th class="px-4 py-3.5">Lần đăng nhập cuối</th><th class="px-4 py-3.5 text-right">Thao tác</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading"><td colspan="6" class="px-5 py-12 text-center text-slate-400"><LoaderCircle class="mx-auto animate-spin" :size="20" /><span class="mt-2 block text-xs">Đang tải tài khoản...</span></td></tr>
            <tr v-else-if="visibleUsers.length === 0"><td colspan="6" class="px-5 py-14 text-center"><UserRound class="mx-auto text-slate-300" :size="24" /><p class="mt-3 text-sm text-slate-500">Chưa có tài khoản phù hợp.</p></td></tr>
            <tr v-for="user in visibleUsers" :key="user.id" class="transition hover:bg-slate-50/70">
              <td class="px-5 py-4"><div class="flex items-center gap-3"><div class="flex size-9 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-900">{{ user.name.slice(0, 1).toUpperCase() }}</div><div><p class="font-medium text-slate-800">{{ user.name }}</p><p class="mt-0.5 text-[11px] text-slate-400">{{ user.code }} · {{ user.phone || 'Chưa có SĐT' }}</p></div></div></td>
              <td class="px-4 py-4"><p class="text-xs font-medium text-slate-700">{{ user.username || '—' }}</p><p class="mt-1 text-xs text-slate-400">{{ user.email }}</p></td>
              <td class="px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600"><ShieldCheck :size="13" />{{ roleLabel(user.role) }}</span></td>
              <td class="px-4 py-4"><span class="inline-flex items-center gap-1.5 text-xs" :class="user.is_active ? 'text-emerald-700' : 'text-rose-600'"><span class="size-1.5 rounded-full" :class="user.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>{{ user.is_active ? 'Đang hoạt động' : 'Đã khóa' }}</span></td>
              <td class="px-4 py-4 text-xs text-slate-500">{{ formatDate(user.last_login_at) }}</td>
              <td class="px-4 py-4"><div class="flex justify-end gap-1"><button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-emerald-800" title="Chỉnh sửa" @click="openEdit(user)"><Pencil :size="14" /></button><button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-emerald-800" title="Đặt lại mật khẩu" @click="resetTarget = user; isResetOpen = true"><KeyRound :size="14" /></button><button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-emerald-800" title="Lịch sử hoạt động" @click="showActivity(user)"><History :size="14" /></button><button class="inline-flex items-center gap-1.5 rounded-lg px-2 py-2 text-xs transition hover:bg-slate-100" :class="user.is_active ? 'text-rose-600' : 'text-emerald-700'" @click="toggleActive(user)"><CircleOff v-if="user.is_active" :size="14" /><CircleCheck v-else :size="14" />{{ user.is_active ? 'Khóa' : 'Mở khóa' }}</button></div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">Hiển thị {{ users.length }} tài khoản gần nhất</div>
    </section>

    <div v-if="isCreateOpen" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="isCreateOpen = false">
      <section class="mobile-modal-panel w-full max-w-lg rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">{{ editingUserId ? 'Cập nhật thông tin' : 'Tài khoản mới' }}</p><h2 class="mt-1 text-xl font-semibold text-slate-900">{{ editingUserId ? 'Chỉnh sửa tài khoản' : 'Tạo tài khoản nhân sự' }}</h2></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Đóng" @click="isCreateOpen = false"><X :size="18" /></button></div>
        <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="saveUser"><label class="field-label">Họ và tên<input v-model="form.name" required class="form-control" /></label><label class="field-label">Username<input v-model="form.username" required minlength="3" class="form-control" /></label><label class="field-label">Email<input v-model="form.email" required type="email" class="form-control" /></label><label class="field-label">Số điện thoại<input v-model="form.phone" class="form-control" /></label><label class="field-label">Vai trò<select v-model="form.role" class="form-control"><option value="STAFF">Nhân viên</option><option value="MANAGER">Quản lý</option><option value="ADMIN">Quản trị viên</option></select></label><label v-if="editingUserId === null" class="field-label sm:col-span-2">Mật khẩu tạm thời<input v-model="form.password" required minlength="12" type="password" autocomplete="new-password" class="form-control" /><span class="mt-1 block text-[11px] font-normal text-slate-400">Tối thiểu 12 ký tự. Gửi mật khẩu cho nhân sự qua kênh an toàn.</span></label><div class="flex flex-col-reverse gap-2 pt-2 sm:col-span-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="isCreateOpen = false">Hủy</button><button :disabled="saving" class="inline-flex items-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"><KeyRound :size="15" />{{ saving ? 'Đang lưu...' : editingUserId ? 'Lưu thay đổi' : 'Tạo tài khoản' }}</button></div></form>
      </section>
    </div>
    <div v-if="isResetOpen && resetTarget" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="isResetOpen = false"><section class="mobile-modal-panel w-full max-w-md rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><h2 class="text-xl font-semibold text-slate-900">Đặt lại mật khẩu</h2><p class="mt-1 text-sm text-slate-500">Tài khoản: {{ resetTarget.name }}</p><form class="mt-5 space-y-4" @submit.prevent="submitPasswordReset"><label class="field-label">Mật khẩu mới<input v-model="resetPassword" minlength="12" required type="password" autocomplete="new-password" class="form-control" /></label><div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="isResetOpen = false">Hủy</button><button class="rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white">Đặt lại</button></div></form></section></div>
    <div v-if="isActivityOpen && activityTarget" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="isActivityOpen = false"><section class="mobile-modal-panel w-full max-w-2xl rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><h2 class="text-xl font-semibold text-slate-900">Lịch sử hoạt động</h2><p class="mt-1 text-sm text-slate-500">{{ activityTarget.name }}</p></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Đóng" @click="isActivityOpen = false"><X :size="18" /></button></div><div v-if="activityRows.length" class="mt-5 divide-y divide-slate-100"> <article v-for="(entry, index) in activityRows" :key="index" class="py-3"><div class="flex justify-between gap-3"><p class="text-sm font-medium text-slate-700">{{ entry.action }}</p><time class="shrink-0 text-xs text-slate-400">{{ formatDate(entry.created_at) }}</time></div><p v-if="entry.after" class="mt-1 break-all text-[11px] text-slate-500">{{ JSON.stringify(entry.after) }}</p></article></div><p v-else class="py-12 text-center text-sm text-slate-400">Chưa có nhật ký hoạt động.</p></section></div>
  </div>
</template>
