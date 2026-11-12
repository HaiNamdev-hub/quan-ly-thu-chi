<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { CalendarDays, CircleCheck, FilePlus2, LoaderCircle, Pencil, Send, Trash2, UnlockKeyhole, X, Ship } from 'lucide-vue-next'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'
import type { UserRole } from '../../types/auth'

interface GuideOption { id: number; name: string; phone: string | null }
interface TourPhoto { photo_type: string; quantity: number }
interface TourRow {
  id: number; code: string; tour_date: string; guide_id: number | null; created_by: number; total_guests: number
  vietnamese_guests: number; international_guests: number; ticket_revenue?: number; ticket_cost?: number; boat_cost?: number
  photo_revenue?: number; status: string; ticket_payment_method: string | null; guide: GuideOption | null; photos?: TourPhoto[]; notes: string | null
}
const auth = useAuthStore()
const isStaff = computed(() => auth.user?.role === 'STAFF')
const canManage = computed(() => auth.user?.role !== 'STAFF')
const selectedDate = ref(localDate())
const tours = ref<TourRow[]>([])
const guides = ref<GuideOption[]>([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const isFormOpen = ref(false)
const editingId = ref<number | null>(null)

const photoTypes = [
  { key: 'large_frame', label: 'Khung lớn' },
  { key: 'small_frame', label: 'Khung nhỏ' },
  { key: 'large_plastic', label: 'Plastic lớn' },
  { key: 'small_plastic', label: 'Plastic nhỏ' },
]
const form = reactive({
  tour_date: localDate(), guide_id: '', vietnamese_guests: 0, international_guests: 0,
  ticket_payment_method: '', coconut_portions: 0, ao_dai_units: 0, ao_dai_revenue: 0, other_expenses: 0, notes: '',
  photos: { large_frame: 0, small_frame: 0, large_plastic: 0, small_plastic: 0 },
})
function localDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}
function resetForm(): void {
  Object.assign(form, { tour_date: selectedDate.value, guide_id: '', vietnamese_guests: 0, international_guests: 0, ticket_payment_method: '', coconut_portions: 0, ao_dai_units: 0, ao_dai_revenue: 0, other_expenses: 0, notes: '', photos: { large_frame: 0, small_frame: 0, large_plastic: 0, small_plastic: 0 } })
}
async function loadTours(): Promise<void> {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get<{ data: TourRow[] }>('/api/tours', { params: isStaff.value ? {} : { date: selectedDate.value } })
    tours.value = data.data
  } catch {
    errorMessage.value = 'Không tải được danh sách đoàn.'
  } finally {
    loading.value = false
  }
}
async function loadGuides(): Promise<void> {
  try {
    const { data } = await api.get<{ data: GuideOption[] }>('/api/guides')
    guides.value = data.data
  } catch { guides.value = [] }
}
watch(selectedDate, loadTours)
onMounted(() => { void loadTours(); void loadGuides() })
function openCreate(): void { editingId.value = null; resetForm(); isFormOpen.value = true }
async function openEdit(tour: TourRow): Promise<void> {
  try {
    const { data } = await api.get<{ data: TourRow }>(`/api/tours/${tour.id}`)
    const detail = data.data
    editingId.value = tour.id
    Object.assign(form, {
      tour_date: detail.tour_date.slice(0, 10), guide_id: detail.guide_id ? String(detail.guide_id) : '',
      vietnamese_guests: detail.vietnamese_guests, international_guests: detail.international_guests,
      ticket_payment_method: detail.ticket_payment_method ?? '', coconut_portions: 0, ao_dai_units: 0,
      ao_dai_revenue: 0, other_expenses: 0, notes: detail.notes ?? '',
      photos: { large_frame: 0, small_frame: 0, large_plastic: 0, small_plastic: 0 },
    })
    for (const photo of detail.photos ?? []) if (photo.photo_type in form.photos) form.photos[photo.photo_type as keyof typeof form.photos] = photo.quantity
    isFormOpen.value = true
  } catch { errorMessage.value = 'Không tải được chi tiết đoàn.' }
}
async function saveTour(): Promise<void> {
  saving.value = true
  errorMessage.value = ''
  const payload = { ...form, guide_id: form.guide_id ? Number(form.guide_id) : null, ticket_payment_method: form.ticket_payment_method || null }
  try {
    if (editingId.value) await api.put(`/api/tours/${editingId.value}`, payload)
    else await api.post('/api/tours', payload)
    isFormOpen.value = false
    selectedDate.value = form.tour_date
    await loadTours()
  } catch {
    errorMessage.value = 'Không lưu được đoàn. Kiểm tra ngày, số khách, HDV và cấu hình giá của ngày đoàn.'
  } finally { saving.value = false }
}
async function actOnTour(tour: TourRow, action: string): Promise<void> {
  if (action === 'delete' && !window.confirm(`Chuyển đoàn ${tour.code} vào thùng lưu trữ?`)) return
  if (action === 'unlock') {
    const reason = window.prompt('Nhập lý do mở khóa đoàn đã chốt:')
    if (!reason) return
    try { await api.post(`/api/tours/${tour.id}/unlock`, { reason }) } catch { errorMessage.value = 'Không mở khóa được đoàn.'; return }
    await loadTours(); return
  }
  try {
    if (action === 'delete') await api.delete(`/api/tours/${tour.id}`)
    else await api.post(`/api/tours/${tour.id}/${action}`)
    await loadTours()
  } catch { errorMessage.value = 'Thao tác không thành công. Hãy tải lại danh sách và kiểm tra trạng thái đoàn.' }
}
function statusLabel(status: string): string { return ({ DRAFT: 'Nháp', PENDING: 'Chờ duyệt', APPROVED: 'Đã duyệt', LOCKED: 'Đã chốt' } as Record<string, string>)[status] ?? status }
function money(value?: number): string { return new Intl.NumberFormat('vi-VN').format(value ?? 0) + ' ₫' }
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Vận hành tour</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Quản lý đoàn khách</h1><p class="mt-1.5 text-sm text-slate-500">Mã đoàn, doanh thu và chi phí được tính và lưu tại máy chủ.</p></div><div class="grid w-full grid-cols-1 gap-2 min-[380px]:grid-cols-[minmax(0,1fr)_auto] sm:w-auto sm:flex"><label v-if="!isStaff" class="flex min-h-11 min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3"><CalendarDays :size="16" class="shrink-0 text-slate-400" /><input v-model="selectedDate" type="date" class="h-10 w-full min-w-0 bg-transparent text-sm text-slate-700 outline-none" /></label><button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white" @click="openCreate"><FilePlus2 :size="16" />Tạo đoàn</button></div></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-col items-start gap-2 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"><div class="min-w-0"><h2 class="font-semibold text-slate-900">Danh sách trong ngày</h2><p class="mt-1 text-xs text-slate-500">{{ isStaff ? selectedDate : selectedDate }} · {{ tours.length }} đoàn</p></div><div class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">Tối đa 20 đoàn/ngày</div></div>
      <div v-if="loading" class="px-5 py-16 text-center text-sm text-slate-400"><LoaderCircle class="mx-auto animate-spin" :size="21" /><p class="mt-2">Đang tải đoàn...</p></div>
      <div v-else-if="tours.length === 0" class="px-5 py-16 text-center"><Ship class="mx-auto text-slate-300" :size="26" /><p class="mt-3 text-sm font-medium text-slate-600">Chưa có đoàn trong ngày này</p><p class="mt-1 text-xs text-slate-400">Tạo đoàn mới để bắt đầu ghi nhận chuyến đi.</p></div>
      <div v-else class="divide-y divide-slate-100">
        <article v-for="tour in tours" :key="tour.id" class="flex flex-col gap-4 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
          <div class="flex min-w-0 items-start gap-4"><div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#edf5ef] text-[#245844]"><Ship :size="20" /></div><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-900">{{ tour.code }}</h3><span class="rounded-full px-2.5 py-1 text-[10px] font-medium" :class="tour.status === 'LOCKED' ? 'bg-slate-100 text-slate-600' : tour.status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : tour.status === 'PENDING' ? 'bg-amber-100 text-amber-800' : 'bg-blue-50 text-blue-700'">{{ statusLabel(tour.status) }}</span></div><p class="mt-1 text-xs text-slate-500">{{ tour.guide?.name ?? 'Chưa phân HDV' }} <span v-if="tour.guide?.phone">· {{ tour.guide.phone }}</span> · {{ tour.vietnamese_guests }} khách Việt, {{ tour.international_guests }} khách quốc tế</p></div></div>
          <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-4 xl:min-w-[450px]"><div><p class="text-slate-400">Tổng khách</p><p class="mt-1 font-semibold text-slate-700">{{ tour.total_guests }}</p></div><div v-if="canManage"><p class="text-slate-400">Doanh thu vé</p><p class="mt-1 font-semibold text-slate-700">{{ money(tour.ticket_revenue) }}</p></div><div v-if="canManage"><p class="text-slate-400">Chi vé + chèo</p><p class="mt-1 font-semibold text-slate-700">{{ money((tour.ticket_cost ?? 0) + (tour.boat_cost ?? 0)) }}</p></div><div><p class="text-slate-400">Hình thức thu vé</p><p class="mt-1 font-medium text-slate-700">{{ ({ cash: 'Tiền mặt', company_transfer: 'CK công ty', personal_transfer: 'CK cá nhân' } as Record<string, string>)[tour.ticket_payment_method ?? ''] ?? 'Chưa phân loại' }}</p></div></div>
          <div class="flex flex-wrap gap-1.5 xl:justify-end"><button v-if="tour.status !== 'LOCKED' && (!isStaff || tour.created_by === auth.user?.id)" class="action-icon" title="Chỉnh sửa" @click="openEdit(tour)"><Pencil :size="15" /></button><button v-if="isStaff && tour.created_by === auth.user?.id && tour.status === 'DRAFT'" class="action-button text-amber-700" @click="actOnTour(tour, 'submit')"><Send :size="14" />Gửi duyệt</button><button v-if="canManage && ['DRAFT','PENDING'].includes(tour.status)" class="action-button text-emerald-800" @click="actOnTour(tour, 'approve')"><CircleCheck :size="14" />Duyệt</button><button v-if="canManage && tour.status === 'APPROVED'" class="action-button text-slate-700" @click="actOnTour(tour, 'lock')"><CircleCheck :size="14" />Chốt đoàn</button><button v-if="auth.user?.role === 'ADMIN' && tour.status === 'LOCKED'" class="action-icon text-amber-700" title="Mở khóa" @click="actOnTour(tour, 'unlock')"><UnlockKeyhole :size="15" /></button><button v-if="canManage && tour.status !== 'LOCKED'" class="action-icon text-rose-600" title="Xóa mềm" @click="actOnTour(tour, 'delete')"><Trash2 :size="15" /></button></div>
        </article>
      </div>
    </section>

    <div v-if="isFormOpen" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="isFormOpen = false"><section class="mobile-modal-panel w-full max-w-3xl rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Thông tin chuyến</p><h2 class="mt-1 text-xl font-semibold text-slate-900">{{ editingId ? 'Cập nhật đoàn' : 'Tạo đoàn mới' }}</h2></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Đóng" @click="isFormOpen = false"><X :size="18" /></button></div>
      <form class="mt-5 grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="saveTour"><label class="field-label">Ngày đoàn<input v-model="form.tour_date" required type="date" class="form-control" /></label><label class="field-label">Hướng dẫn viên<select v-model="form.guide_id" class="form-control"><option value="">Chưa phân HDV</option><option v-for="guide in guides" :key="guide.id" :value="String(guide.id)">{{ guide.name }}{{ guide.phone ? ` · ${guide.phone}` : '' }}</option></select></label><label class="field-label">Khách Việt<input v-model.number="form.vietnamese_guests" required min="0" max="1000" type="number" inputmode="numeric" class="form-control" /></label><label class="field-label">Khách quốc tế<input v-model.number="form.international_guests" required min="0" max="1000" type="number" inputmode="numeric" class="form-control" /></label><label class="field-label">Hình thức thu vé<select v-model="form.ticket_payment_method" class="form-control"><option value="">Chưa phân loại</option><option value="cash">Tiền mặt</option><option value="company_transfer">Chuyển khoản công ty</option><option value="personal_transfer">Chuyển khoản cá nhân</option></select></label><label class="field-label">Chi phí khác (đ)<input v-model.number="form.other_expenses" min="0" type="number" inputmode="numeric" class="form-control" /></label>
        <div class="sm:col-span-2"><p class="mb-2 text-xs font-medium text-slate-600">Số lượng ảnh bán</p><div class="grid grid-cols-2 gap-3 sm:grid-cols-4"><label v-for="photo in photoTypes" :key="photo.key" class="field-label">{{ photo.label }}<input v-model.number="form.photos[photo.key as keyof typeof form.photos]" min="0" type="number" class="form-control" /></label></div><p class="mt-2 text-[11px] text-slate-400">Giá ảnh áp dụng theo ngày đoàn và được lưu snapshot.</p></div>
        <label class="field-label">Số phần chè dừa<input v-model.number="form.coconut_portions" min="0" type="number" inputmode="numeric" class="form-control" /><span class="text-[11px] font-normal text-slate-400">Doanh thu chè theo dõi ngoài hệ thống.</span></label><label class="field-label">Số đơn vị áo dài<input v-model.number="form.ao_dai_units" min="0" type="number" inputmode="numeric" class="form-control" /></label><label class="field-label">Doanh thu áo dài (đ)<input v-model.number="form.ao_dai_revenue" min="0" type="number" inputmode="numeric" class="form-control" /></label><label class="field-label sm:col-span-2">Ghi chú<textarea v-model="form.notes" rows="2" maxlength="5000" class="form-control h-auto py-2" /></label><div class="flex flex-col-reverse gap-2 pt-2 sm:col-span-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="isFormOpen = false">Hủy</button><button :disabled="saving" class="rounded-xl bg-[#174b3e] px-5 py-2.5 text-sm font-medium text-white disabled:opacity-60">{{ saving ? 'Đang lưu...' : 'Lưu đoàn' }}</button></div>
      </form></section></div>
  </div>
</template>
