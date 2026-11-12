<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { CalendarDays, ChartNoAxesCombined, CircleAlert, RefreshCw } from 'lucide-vue-next'
import VueApexCharts from 'vue3-apexcharts'
import type { ApexOptions } from 'apexcharts'
import { api } from '../../services/api'

interface Summary {
  revenue: number; operating_expenses: number; net_before_photo_cost: number; profit_after_photo_cost: number
  cash_expected: number; company_transfer: number; personal_transfer: number; ticket_unclassified: number
  tour_count: number; total_guests: number
}
interface PendingRecords {
  tours_pending_reconciliation: number
  ready_import_rows_awaiting_confirmation: number
  rows_needing_review: number
  batches_awaiting_analysis: number
}
interface DailyPoint { date: string; revenue: number; operating_expenses: number; profit: number }
interface DashboardResponse { period: { mode: string; from: string; to: string }; summary: Summary; pending_records: PendingRecords; daily: DailyPoint[]; cash_reconciliation: { variance: number; is_actual_balance: boolean; reconciliation_total: number } }
const reportDate = ref(localDate())
const mode = ref<'day' | 'month' | 'cumulative'>('day')
const summary = ref<Summary | null>(null)
const pendingRecords = ref<PendingRecords | null>(null)
const daily = ref<DailyPoint[]>([])
const period = ref<DashboardResponse['period'] | null>(null)
const reconciliation = ref<DashboardResponse['cash_reconciliation'] | null>(null)
const loading = ref(false)
const errorMessage = ref('')
const chartOptions = computed<ApexOptions>(() => ({
  chart: { type: 'area', toolbar: { show: false }, fontFamily: 'Inter, "Be Vietnam Pro", "Segoe UI", Arial, sans-serif', foreColor: '#748178', redrawOnParentResize: true, redrawOnWindowResize: true },
  colors: ['#1c7050', '#d39850', '#527b91'],
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.18, opacityTo: 0.02 } },
  grid: { borderColor: '#edf1ed', strokeDashArray: 4 },
  xaxis: { categories: daily.value.map((point) => point.date.slice(5)), labels: { rotate: -40, hideOverlappingLabels: true, trim: true } },
  yaxis: { labels: { formatter: (value) => new Intl.NumberFormat('vi-VN', { notation: 'compact' }).format(value) } },
  legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px' },
  tooltip: { y: { formatter: (value) => money(value ?? 0) } },
}))
const chartSeries = computed(() => [
  { name: 'Doanh thu', data: daily.value.map((point) => point.revenue) },
  { name: 'Chi phí hoạt động', data: daily.value.map((point) => point.operating_expenses) },
  { name: 'Lợi nhuận', data: daily.value.map((point) => point.profit) },
])
const cards = computed(() => summary.value ? [
  { label: 'Tổng doanh thu', value: summary.value.revenue },
  { label: 'Tổng chi phí hoạt động', value: summary.value.operating_expenses },
  { label: 'Chênh lệch thu – chi', value: summary.value.net_before_photo_cost },
  { label: 'Lợi nhuận sau giá vốn ảnh', value: summary.value.profit_after_photo_cost },
  { label: 'Tiền mặt két dự kiến', value: summary.value.cash_expected },
  { label: 'CK công ty', value: summary.value.company_transfer },
  { label: 'CK cá nhân', value: summary.value.personal_transfer },
  { label: 'Vé chưa phân loại', value: summary.value.ticket_unclassified },
] : [])
function localDate(): string {
  const date = new Date()
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
function money(value: number): string { return new Intl.NumberFormat('vi-VN').format(value) + ' ₫' }
async function loadDashboard(): Promise<void> {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get<DashboardResponse>('/api/dashboard/summary', { params: { mode: mode.value, date: reportDate.value } })
    summary.value = data.summary
    pendingRecords.value = data.pending_records
    daily.value = data.daily
    period.value = data.period
    reconciliation.value = data.cash_reconciliation
  } catch { errorMessage.value = 'Không tải được báo cáo. Vui lòng thử lại.' }
  finally { loading.value = false }
}
watch([reportDate, mode], loadDashboard)
onMounted(loadDashboard)
</script>

<template>
  <div class="mx-auto min-w-0 max-w-7xl space-y-6 sm:space-y-7">
    <div class="flex min-w-0 flex-col justify-between gap-4 xl:flex-row xl:items-end"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Bảng điều hành</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Tổng quan kinh doanh</h1><p class="mt-1.5 break-words text-sm text-slate-500">{{ period ? `${period.from} – ${period.to}` : 'Theo dõi hoạt động theo ngày báo cáo.' }}</p></div><div class="grid min-w-0 grid-cols-1 gap-2 min-[380px]:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:flex"><select v-model="mode" class="min-h-11 w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-600 outline-none"><option value="day">Theo ngày</option><option value="month">Tháng đến ngày chọn</option><option value="cumulative">Lũy kế từ 24/10/2026</option></select><label class="flex min-h-11 min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 sm:flex-none"><CalendarDays :size="16" class="shrink-0 text-slate-400" /><input v-model="reportDate" type="date" class="min-h-10 w-full min-w-0 bg-transparent text-sm text-slate-700 outline-none" /></label></div></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <section class="grid min-w-0 grid-cols-1 gap-3 min-[380px]:grid-cols-2 xl:grid-cols-4 xl:gap-4"><article v-for="card in cards" :key="card.label" class="min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm shadow-slate-900/[0.02] sm:p-5"><div class="flex min-h-10 items-start justify-between gap-2"><span class="min-w-0 text-sm leading-5 text-slate-500">{{ card.label }}</span><span class="mt-1 size-2 shrink-0 rounded-full bg-emerald-600/70"></span></div><p class="mt-3 break-words text-xl font-semibold leading-tight tracking-tight tabular-nums sm:mt-5 sm:text-2xl" :class="card.value < 0 ? 'text-rose-700' : 'text-slate-900'">{{ money(card.value) }}</p><p class="mt-2 text-[11px] text-slate-400">Đoàn đã duyệt hoặc chốt</p></article></section>
    <section v-if="pendingRecords" aria-label="Dữ liệu import đang chờ" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:p-5">
      <div class="flex items-start gap-3">
        <CircleAlert :size="18" class="mt-0.5 shrink-0 text-amber-700" />
        <div class="min-w-0 flex-1">
          <h2 class="font-semibold text-amber-950">Dữ liệu chưa đưa vào báo cáo chính</h2>
          <p class="mt-1 text-xs leading-5 text-amber-900/80">Các số dưới đây được giữ riêng, không cộng vào doanh thu hoặc lợi nhuận cho đến khi được xác nhận.</p>
          <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-amber-200/80 bg-white/70 px-3 py-2.5"><p class="text-xs text-amber-900">Đoàn chờ đối soát</p><p class="mt-1 text-lg font-semibold tabular-nums text-amber-950">{{ pendingRecords.tours_pending_reconciliation }}</p></div>
            <div class="rounded-xl border border-amber-200/80 bg-white/70 px-3 py-2.5"><p class="text-xs text-amber-900">Dòng sẵn sàng chờ Admin xác nhận</p><p class="mt-1 text-lg font-semibold tabular-nums text-amber-950">{{ pendingRecords.ready_import_rows_awaiting_confirmation }}</p></div>
            <div class="rounded-xl border border-amber-200/80 bg-white/70 px-3 py-2.5"><p class="text-xs text-amber-900">Dòng cần đối soát</p><p class="mt-1 text-lg font-semibold tabular-nums text-amber-950">{{ pendingRecords.rows_needing_review }}</p></div>
            <div class="rounded-xl border border-amber-200/80 bg-white/70 px-3 py-2.5"><p class="text-xs text-amber-900">Workbook chờ phân tích</p><p class="mt-1 text-lg font-semibold tabular-nums text-amber-950">{{ pendingRecords.batches_awaiting_analysis }}</p></div>
          </div>
        </div>
      </div>
    </section>
    <section class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(18rem,1fr)] xl:gap-5"><div class="min-w-0 overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-6"><div class="flex min-w-0 items-start justify-between gap-3"><div class="min-w-0"><h2 class="font-semibold text-slate-900">Doanh thu, chi phí và lợi nhuận</h2><p class="mt-1 text-xs text-slate-500">Số liệu tính từ API theo phạm vi ngày đã chọn.</p></div><ChartNoAxesCombined :size="19" class="shrink-0 text-emerald-800" /></div><div v-if="loading" class="flex h-64 items-center justify-center text-slate-300"><RefreshCw class="animate-spin" :size="22" /></div><VueApexCharts v-else-if="daily.length" class="mt-4 min-w-0" type="area" height="280" :options="chartOptions" :series="chartSeries" /><div v-else class="mt-6 flex h-56 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 text-center"><ChartNoAxesCombined :size="21" class="text-slate-300" /><p class="mt-3 text-sm text-slate-500">Chưa có đoàn đã duyệt trong kỳ này.</p></div></div>
      <div class="min-w-0 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-6"><div class="flex min-w-0 items-start justify-between gap-3"><div class="min-w-0"><h2 class="font-semibold text-slate-900">Đối chiếu kênh tiền</h2><p class="mt-1 text-xs text-slate-500">Tiền vé theo phương thức thu.</p></div><span v-if="reconciliation" class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium" :class="reconciliation.variance === 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">{{ reconciliation.variance === 0 ? 'Khớp tổng' : 'Có sai lệch' }}</span></div><div class="mt-5 space-y-3"><div v-for="item in [
        { label: 'Tiền mặt két dự kiến', value: summary?.cash_expected },
        { label: 'Chuyển khoản công ty', value: summary?.company_transfer },
        { label: 'Chuyển khoản cá nhân', value: summary?.personal_transfer },
        { label: 'Vé chưa phân loại', value: summary?.ticket_unclassified },
      ]" :key="item.label" class="flex min-w-0 items-start justify-between gap-3 rounded-xl bg-slate-50 px-3 py-3 sm:px-4"><span class="min-w-0 text-xs leading-5 text-slate-600">{{ item.label }}</span><span class="max-w-[58%] break-words text-right text-xs font-semibold tabular-nums text-slate-800">{{ loading || item.value === undefined ? '—' : money(item.value) }}</span></div></div><div class="mt-4 flex items-start gap-2 rounded-xl border border-blue-100 bg-blue-50/70 p-3"><CircleAlert :size="15" class="mt-0.5 shrink-0 text-blue-700" /><p class="text-[11px] leading-5 text-blue-800">Số dư két là số dự kiến theo giao dịch đã ghi nhận, không phải số dư thực tế.</p></div><p class="mt-4 text-[11px] text-slate-400">{{ summary?.tour_count ?? '—' }} đoàn · {{ summary?.total_guests ?? '—' }} khách</p></div>
    </section>
  </div>
</template>
