<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ContactRound, History, LoaderCircle, Pencil, Plus, Search, X } from 'lucide-vue-next'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

interface Guide { id: number; name: string; phone: string | null; notes: string | null; tours_count?: number; tours_sum_total_guests?: number; tours_sum_ticket_revenue?: number }
interface GuideTour { id: number; code: string; tour_date: string; total_guests: number; ticket_revenue: number; door_commission: number; photos: Array<{ revenue: number; cost: number }> }
const auth = useAuthStore()
const canEdit = computed(() => auth.user?.role !== 'STAFF')
const guides = ref<Guide[]>([])
const search = ref('')
const loading = ref(false)
const errorMessage = ref('')
const showForm = ref(false)
const editingId = ref<number | null>(null)
const historyGuide = ref<Guide | null>(null)
const tourHistory = ref<GuideTour[]>([])
const form = reactive({ name: '', phone: '', notes: '' })

async function loadGuides(): Promise<void> {
  loading.value = true
  try {
    const { data } = await api.get<{ data: Guide[] }>('/api/guides', { params: { search: search.value || undefined } })
    guides.value = data.data
  } catch { errorMessage.value = 'Không tải được danh sách hướng dẫn viên.' }
  finally { loading.value = false }
}
let debounce: ReturnType<typeof setTimeout>
watch(search, () => { clearTimeout(debounce); debounce = setTimeout(loadGuides, 250) })
onMounted(loadGuides)
function newGuide(): void { editingId.value = null; Object.assign(form, { name: '', phone: '', notes: '' }); showForm.value = true }
function editGuide(guide: Guide): void { editingId.value = guide.id; Object.assign(form, { name: guide.name, phone: guide.phone ?? '', notes: guide.notes ?? '' }); showForm.value = true }
async function saveGuide(): Promise<void> {
  errorMessage.value = ''
  try {
    if (editingId.value) await api.put(`/api/guides/${editingId.value}`, form)
    else await api.post('/api/guides', form)
    showForm.value = false
    await loadGuides()
  } catch { errorMessage.value = 'Không lưu được thông tin. Hãy kiểm tra tên và số điện thoại.' }
}
async function openHistory(guide: Guide): Promise<void> {
  historyGuide.value = guide
  try {
    const { data } = await api.get<{ data: { data: GuideTour[] } }>(`/api/guides/${guide.id}/history`)
    tourHistory.value = data.data.data
  } catch { errorMessage.value = 'Không tải được lịch sử đoàn của hướng dẫn viên.' }
}
function money(value: number): string { return new Intl.NumberFormat('vi-VN').format(value) + ' ₫' }
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Đội ngũ vận hành</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Hướng dẫn viên</h1><p class="mt-1.5 text-sm text-slate-500">Tra cứu tên, số điện thoại và lịch sử đoàn.</p></div><button v-if="canEdit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white" @click="newGuide"><Plus :size="16" />Thêm HDV</button></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-4"><div class="relative max-w-md"><Search class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" :size="16" /><input v-model="search" class="h-10 w-full rounded-xl border border-slate-200 pl-9 pr-3 text-sm outline-none focus:border-emerald-700" placeholder="Tìm tên hoặc số điện thoại" /></div></div>
      <div v-if="loading" class="px-5 py-14 text-center text-sm text-slate-400"><LoaderCircle class="mx-auto animate-spin" :size="20" /><span class="mt-2 block">Đang tải HDV...</span></div>
      <div v-else-if="guides.length === 0" class="px-5 py-14 text-center"><ContactRound class="mx-auto text-slate-300" :size="24" /><p class="mt-3 text-sm text-slate-500">Chưa có hướng dẫn viên phù hợp.</p></div>
      <div v-else class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3.5">Hướng dẫn viên</th><th class="px-4 py-3.5">Số lần ghé</th><th class="px-4 py-3.5">Tổng khách</th><th class="px-4 py-3.5">Doanh thu vé</th><th class="px-4 py-3.5 text-right">Thao tác</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="guide in guides" :key="guide.id" class="hover:bg-slate-50"><td class="px-5 py-4"><div class="flex items-center gap-3"><div class="flex size-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-900"><ContactRound :size="16" /></div><div><p class="font-medium text-slate-800">{{ guide.name }}</p><p class="mt-0.5 text-xs text-slate-400">{{ guide.phone || 'Chưa có số điện thoại' }}</p></div></div></td><td class="px-4 py-4 text-slate-600">{{ guide.tours_count ?? '—' }}</td><td class="px-4 py-4 text-slate-600">{{ guide.tours_sum_total_guests ?? '—' }}</td><td class="px-4 py-4 text-slate-600">{{ guide.tours_sum_ticket_revenue !== undefined ? money(guide.tours_sum_ticket_revenue) : '—' }}</td><td class="px-4 py-4"><div class="flex justify-end gap-1"><button v-if="canEdit" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Chỉnh sửa" @click="editGuide(guide)"><Pencil :size="14" /></button><button v-if="canEdit" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" title="Lịch sử đoàn" @click="openHistory(guide)"><History :size="14" /></button></div></td></tr></tbody></table></div>
    </section>
    <div v-if="showForm" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="showForm = false"><section class="mobile-modal-panel w-full max-w-lg rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Danh bạ HDV</p><h2 class="mt-1 text-xl font-semibold text-slate-900">{{ editingId ? 'Chỉnh sửa HDV' : 'Thêm hướng dẫn viên' }}</h2></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="showForm = false"><X :size="18" /></button></div><form class="mt-5 space-y-4" @submit.prevent="saveGuide"><label class="field-label">Họ và tên<input v-model="form.name" required maxlength="150" class="form-control" /></label><label class="field-label">Số điện thoại<input v-model="form.phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="30" class="form-control" /></label><label class="field-label">Ghi chú<textarea v-model="form.notes" rows="3" class="form-control h-auto py-2" /></label><div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="showForm = false">Hủy</button><button class="rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white">Lưu</button></div></form></section></div>
    <div v-if="historyGuide" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="historyGuide = null"><section class="mobile-modal-panel w-full max-w-2xl rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><h2 class="text-xl font-semibold text-slate-900">Lịch sử các đoàn</h2><p class="mt-1 text-sm text-slate-500">{{ historyGuide.name }}</p></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="historyGuide = null"><X :size="18" /></button></div><div v-if="tourHistory.length" class="mt-5 divide-y divide-slate-100"><article v-for="tour in tourHistory" :key="tour.id" class="flex items-center justify-between gap-3 py-3"><div><p class="text-sm font-medium text-slate-800">{{ tour.code }}</p><p class="mt-1 text-xs text-slate-500">{{ tour.tour_date.slice(0, 10) }} · {{ tour.total_guests }} khách</p></div><div class="text-right"><p class="text-xs text-slate-500">Vé {{ money(tour.ticket_revenue) }}</p><p class="mt-1 text-[11px] text-slate-400">Ảnh {{ money(tour.photos.reduce((sum, row) => sum + row.revenue, 0)) }}</p></div></article></div><p v-else class="py-12 text-center text-sm text-slate-400">Chưa có lịch sử đoàn.</p></section></div>
  </div>
</template>
