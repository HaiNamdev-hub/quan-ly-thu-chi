<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { CalendarDays, LoaderCircle, Plus, ReceiptText, Search, Trash2, X } from 'lucide-vue-next'
import { api } from '../../services/api'

interface ExpenseRow { id: number; category: string; amount: number; occurred_on: string; tour_group_id: number | null; tour?: { id: number; code: string } | null; creator?: { name: string }; notes: string | null }
interface TourOption { id: number; code: string }
const today = new Date()
const toLocalDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const fromDate = ref(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`)
const toDate = ref(toLocalDate(today))
const expenses = ref<ExpenseRow[]>([])
const tourOptions = ref<TourOption[]>([])
const loading = ref(false)
const errorMessage = ref('')
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = reactive({ category: '', amount: 0, occurred_on: toLocalDate(today), tour_group_id: '', notes: '', reason: '' })
const total = computed(() => expenses.value.reduce((sum, expense) => sum + expense.amount, 0))

async function loadExpenses(): Promise<void> {
  loading.value = true
  try {
    const { data } = await api.get<{ data: ExpenseRow[] }>('/api/expenses', { params: { from: fromDate.value, to: toDate.value } })
    expenses.value = data.data
  } catch { errorMessage.value = 'Không tải được sổ khoản chi.' }
  finally { loading.value = false }
}
async function loadTours(): Promise<void> {
  try {
    const { data } = await api.get<{ data: TourOption[] }>('/api/tours', { params: { date: form.occurred_on } })
    tourOptions.value = data.data
  } catch { tourOptions.value = [] }
}
watch([fromDate, toDate], loadExpenses)
watch(() => form.occurred_on, loadTours)
onMounted(() => { void loadExpenses(); void loadTours() })
function openCreate(): void { editingId.value = null; Object.assign(form, { category: '', amount: 0, occurred_on: toDate.value, tour_group_id: '', notes: '', reason: '' }); showForm.value = true }
function openEdit(expense: ExpenseRow): void { editingId.value = expense.id; Object.assign(form, { category: expense.category, amount: expense.amount, occurred_on: expense.occurred_on.slice(0, 10), tour_group_id: expense.tour_group_id ? String(expense.tour_group_id) : '', notes: expense.notes ?? '', reason: '' }); showForm.value = true }
async function saveExpense(): Promise<void> {
  const payload = { ...form, tour_group_id: form.tour_group_id ? Number(form.tour_group_id) : null }
  try {
    if (editingId.value) await api.put(`/api/expenses/${editingId.value}`, payload)
    else await api.post('/api/expenses', payload)
    showForm.value = false
    await loadExpenses()
  } catch { errorMessage.value = 'Không lưu được khoản chi. Nếu đoàn đã chốt, Admin cần nhập lý do điều chỉnh.' }
}
async function removeExpense(expense: ExpenseRow): Promise<void> {
  const reason = window.prompt(`Lý do xóa mềm khoản ${expense.category} (${money(expense.amount)}):`)
  if (reason === null) return
  try { await api.delete(`/api/expenses/${expense.id}`, { data: { reason } }); await loadExpenses() }
  catch { errorMessage.value = 'Không xóa được khoản chi. Đoàn đã chốt cần Admin và lý do điều chỉnh.' }
}
function money(amount: number): string { return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫' }
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Sổ tài chính</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Thu chi</h1><p class="mt-1.5 text-sm text-slate-500">Theo dõi chi phí theo ngày, danh mục và đoàn khách.</p></div><button class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white" @click="openCreate"><Plus :size="16" />Thêm khoản chi</button></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <section class="grid gap-4 sm:grid-cols-[1fr_1fr_1.4fr]"><label class="field-label">Từ ngày<input v-model="fromDate" type="date" class="form-control" /></label><label class="field-label">Đến ngày<input v-model="toDate" type="date" class="form-control" /></label><div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Tổng khoản chi trong danh sách</p><p class="mt-1.5 text-xl font-semibold text-slate-900">{{ money(total) }}</p></div></section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div class="flex items-center gap-2"><ReceiptText :size="17" class="text-emerald-800" /><h2 class="font-semibold text-slate-900">Các khoản chi</h2></div><span class="text-xs text-slate-400">{{ expenses.length }} khoản hiển thị</span></div><div v-if="loading" class="py-14 text-center text-slate-400"><LoaderCircle class="mx-auto animate-spin" :size="20" /><span class="mt-2 block text-xs">Đang tải...</span></div><div v-else-if="expenses.length === 0" class="py-14 text-center"><Search class="mx-auto text-slate-300" :size="22" /><p class="mt-3 text-sm text-slate-500">Không có khoản chi trong khoảng ngày này.</p></div><div v-else class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3.5">Ngày phát sinh</th><th class="px-4 py-3.5">Danh mục / ghi chú</th><th class="px-4 py-3.5">Đoàn</th><th class="px-4 py-3.5">Người nhập</th><th class="px-4 py-3.5 text-right">Số tiền</th><th class="px-5 py-3.5 text-right">Thao tác</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="expense in expenses" :key="expense.id" class="hover:bg-slate-50"><td class="px-5 py-4 text-xs text-slate-600">{{ expense.occurred_on.slice(0, 10) }}</td><td class="px-4 py-4"><p class="font-medium text-slate-700">{{ expense.category }}</p><p v-if="expense.notes" class="mt-1 max-w-xs truncate text-xs text-slate-400">{{ expense.notes }}</p></td><td class="px-4 py-4 text-xs text-slate-500">{{ expense.tour?.code ?? 'Chi chung' }}</td><td class="px-4 py-4 text-xs text-slate-500">{{ expense.creator?.name ?? '—' }}</td><td class="px-4 py-4 text-right font-medium text-slate-700">{{ money(expense.amount) }}</td><td class="px-5 py-4"><div class="flex justify-end gap-1"><button class="rounded-lg px-2.5 py-2 text-xs text-slate-600 hover:bg-slate-100" @click="openEdit(expense)">Sửa</button><button class="rounded-lg p-2 text-rose-600 hover:bg-rose-50" title="Xóa mềm" @click="removeExpense(expense)"><Trash2 :size="14" /></button></div></td></tr></tbody></table></div></section>
    <div v-if="showForm" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="showForm = false"><section class="mobile-modal-panel w-full max-w-lg rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Giao dịch chi</p><h2 class="mt-1 text-xl font-semibold text-slate-900">{{ editingId ? 'Chỉnh sửa khoản chi' : 'Thêm khoản chi' }}</h2></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="showForm = false"><X :size="18" /></button></div><form class="mt-5 space-y-4" @submit.prevent="saveExpense"><label class="field-label">Danh mục<input v-model="form.category" required maxlength="80" placeholder="Ví dụ: Nước uống, sửa chữa..." class="form-control" /></label><div class="grid gap-4 sm:grid-cols-2"><label class="field-label">Số tiền (đ)<input v-model.number="form.amount" required min="1" type="number" inputmode="numeric" class="form-control" /></label><label class="field-label">Ngày phát sinh<input v-model="form.occurred_on" required type="date" class="form-control" /></label></div><label class="field-label">Gắn với đoàn<select v-model="form.tour_group_id" class="form-control"><option value="">Chi chung</option><option v-for="tour in tourOptions" :key="tour.id" :value="String(tour.id)">{{ tour.code }}</option></select></label><label class="field-label">Ghi chú<textarea v-model="form.notes" rows="3" class="form-control h-auto py-2" /></label><label v-if="editingId" class="field-label">Lý do điều chỉnh (bắt buộc với đoàn đã chốt)<input v-model="form.reason" minlength="5" class="form-control" /></label><div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="showForm = false">Hủy</button><button class="rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white">Lưu khoản chi</button></div></form></section></div>
  </div>
</template>
