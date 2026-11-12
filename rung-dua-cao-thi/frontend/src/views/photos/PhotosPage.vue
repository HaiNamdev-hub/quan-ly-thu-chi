<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { CalendarDays, Image, LoaderCircle, Plus, X } from 'lucide-vue-next'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

interface PhotoPrice { photo_type: string; effective_from: string; vietnamese_price: number; international_price: number; unit_cost: number; quantity_sold: number; needs_reconciliation: boolean }
interface PriceHistory extends Omit<PhotoPrice, 'quantity_sold'> { id: number; effective_to: string | null }
const auth = useAuthStore()
const isAdmin = computed(() => auth.user?.role === 'ADMIN')
const date = ref(localDate())
const prices = ref<PhotoPrice[]>([])
const history = ref<PriceHistory[]>([])
const loading = ref(false)
const errorMessage = ref('')
const showForm = ref(false)
const form = ref({ photo_type: 'large_frame', effective_from: localDate(), vietnamese_price: 0, international_price: 0, unit_cost: 0, needs_reconciliation: true })
const typeLabels: Record<string, string> = { large_frame: 'Khung lớn', small_frame: 'Khung nhỏ', large_plastic: 'Plastic lớn', small_plastic: 'Plastic nhỏ' }
function localDate(): string { const today = new Date(); return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}` }
function money(amount: number): string { return new Intl.NumberFormat('vi-VN').format(amount) + ' ₫' }
async function load(): Promise<void> {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await api.get<{ date: string; prices: PhotoPrice[] }>('/api/photos', { params: { date: date.value } })
    prices.value = response.data.prices
    if (isAdmin.value) {
      const records = await api.get<{ data: PriceHistory[] }>('/api/admin/photo-prices')
      history.value = records.data.data
    }
  } catch { errorMessage.value = 'Không tải được bảng giá ảnh.' }
  finally { loading.value = false }
}
watch(date, load)
onMounted(load)
async function savePrice(): Promise<void> {
  try {
    await api.post('/api/admin/photo-prices', form.value)
    showForm.value = false
    await load()
  } catch { errorMessage.value = 'Không thể thêm mức giá. Chọn ngày hiệu lực mới; các mức giá trùng ngày không được ghi đè.' }
}
</script>

<template>
  <div class="mx-auto min-w-0 max-w-7xl space-y-6"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Dịch vụ hình ảnh</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Ảnh bán</h1><p class="mt-1.5 text-sm text-slate-500">Giá và giá vốn được lưu theo thời điểm hiệu lực.</p></div><div class="flex w-full flex-col gap-2 min-[420px]:flex-row sm:w-auto"><label class="flex min-h-11 min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3"><CalendarDays :size="16" class="shrink-0 text-slate-400" /><input v-model="date" type="date" class="h-10 w-full min-w-0 bg-transparent text-sm text-slate-700 outline-none" /></label><button v-if="isAdmin" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white" @click="form.effective_from = date; showForm = true"><Plus :size="16" />Thêm mức giá</button></div></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div class="flex items-center gap-2"><Image :size="17" class="text-emerald-800" /><h2 class="font-semibold text-slate-900">Bảng giá ngày {{ date }}</h2></div><span class="text-xs text-slate-400">{{ prices.length }} loại ảnh</span></div><div v-if="loading" class="py-14 text-center text-slate-400"><LoaderCircle class="mx-auto animate-spin" :size="20" /><span class="mt-2 block text-xs">Đang tải...</span></div><div v-else-if="prices.length === 0" class="py-14 text-center"><Image class="mx-auto text-slate-300" :size="23" /><p class="mt-3 text-sm text-slate-500">Chưa có bảng giá hiệu lực cho ngày này.</p></div><div v-else class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3.5">Loại ảnh</th><th class="px-4 py-3.5">Giá khách Việt</th><th class="px-4 py-3.5">Giá khách quốc tế</th><th class="px-4 py-3.5">Giá vốn</th><th class="px-4 py-3.5">Số lượng ghi nhận</th><th class="px-5 py-3.5">Đối chiếu</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="price in prices" :key="price.photo_type"><td class="px-5 py-4 font-medium text-slate-800">{{ typeLabels[price.photo_type] ?? price.photo_type }}</td><td class="px-4 py-4 text-slate-600">{{ money(price.vietnamese_price) }}</td><td class="px-4 py-4 text-slate-600">{{ money(price.international_price) }}</td><td class="px-4 py-4 text-slate-600">{{ money(price.unit_cost) }}</td><td class="px-4 py-4 text-slate-600">{{ price.quantity_sold }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-medium" :class="price.needs_reconciliation ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800'">{{ price.needs_reconciliation ? 'Cần đối chiếu Excel' : 'Đã xác nhận' }}</span></td></tr></tbody></table></div></section>
    <section v-if="isAdmin" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Lịch sử giá</h2><p class="mt-1 text-xs text-slate-500">Giá mới tạo khoảng hiệu lực mới; dữ liệu đoàn đã lưu không được tính lại.</p></div><div v-if="history.length" class="divide-y divide-slate-100"><article v-for="row in history" :key="row.id" class="grid gap-2 px-5 py-3 text-xs sm:grid-cols-[1fr_1.5fr_1fr]"><p class="font-medium text-slate-700">{{ typeLabels[row.photo_type] }}</p><p class="text-slate-500">{{ money(row.vietnamese_price) }} / {{ money(row.international_price) }} · vốn {{ money(row.unit_cost) }}</p><p class="text-slate-400">{{ row.effective_from }} → {{ row.effective_to ?? 'Hiện tại' }}</p></article></div><p v-else class="py-9 text-center text-sm text-slate-400">Chưa có lịch sử bảng giá.</p></section>
    <div v-if="showForm" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="showForm = false"><section class="mobile-modal-panel w-full max-w-lg rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Bảng giá có hiệu lực</p><h2 class="mt-1 text-xl font-semibold text-slate-900">Thêm mức giá ảnh</h2></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="showForm = false"><X :size="18" /></button></div><form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="savePrice"><label class="field-label sm:col-span-2">Loại ảnh<select v-model="form.photo_type" class="form-control"><option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option></select></label><label class="field-label sm:col-span-2">Ngày hiệu lực<input v-model="form.effective_from" required type="date" class="form-control" /></label><label class="field-label">Giá khách Việt<input v-model.number="form.vietnamese_price" required min="0" type="number" inputmode="numeric" class="form-control" /></label><label class="field-label">Giá khách quốc tế<input v-model.number="form.international_price" required min="0" type="number" inputmode="numeric" class="form-control" /></label><label class="field-label">Giá vốn<input v-model.number="form.unit_cost" required min="0" type="number" inputmode="numeric" class="form-control" /></label><label class="mt-2 flex min-h-11 items-center gap-2 text-xs text-slate-600 sm:mt-6"><input v-model="form.needs_reconciliation" type="checkbox" class="accent-emerald-800" />Đánh dấu cần đối chiếu Excel</label><div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="showForm = false">Hủy</button><button class="rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white">Lưu mức giá</button></div></form></section></div>
  </div>
</template>
