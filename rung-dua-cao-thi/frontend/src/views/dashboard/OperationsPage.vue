<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { CalendarDays, UsersRound, Image, ClipboardList, LoaderCircle, Ship } from 'lucide-vue-next'
import { api } from '../../services/api'

interface Operations { date: string; tour_count: number; total_guests: number; photos_sold: number; incomplete_tours: number; my_tours: number }
const summary = ref<Operations | null>(null)
const errorMessage = ref('')
const loading = ref(true)
const cards = [
  { label: 'Đoàn trong ngày', key: 'tour_count' as const, icon: CalendarDays },
  { label: 'Tổng số khách', key: 'total_guests' as const, icon: UsersRound },
  { label: 'Ảnh đã bán', key: 'photos_sold' as const, icon: Image },
  { label: 'Đoàn cần hoàn thiện', key: 'incomplete_tours' as const, icon: ClipboardList },
]
onMounted(async () => {
  try {
    const { data } = await api.get<{ operations: Operations }>('/api/dashboard/summary')
    summary.value = data.operations
  } catch { errorMessage.value = 'Không tải được dữ liệu vận hành hôm nay.' }
  finally { loading.value = false }
})
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-7"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Không gian nhân viên</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Vận hành hôm nay</h1><p class="mt-1.5 text-sm text-slate-500">{{ summary?.date ?? new Date().toLocaleDateString('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}</p></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article v-for="card in cards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5"><div class="flex items-center justify-between"><p class="text-sm text-slate-500">{{ card.label }}</p><component :is="card.icon" :size="18" class="text-emerald-800" /></div><p class="mt-5 text-2xl font-semibold text-slate-900">{{ loading ? '—' : summary?.[card.key] ?? 0 }}</p><p class="mt-2 text-[11px] text-slate-400">Cập nhật từ dữ liệu vận hành</p></article></section>
    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-10"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold text-slate-900">Đoàn do bạn nhập</h2><p class="mt-1 text-sm text-slate-500">Số đoàn đã tạo trong ngày: {{ loading ? '—' : summary?.my_tours ?? 0 }}</p></div><RouterLink to="/tours" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white"><Ship :size="16" />Mở danh sách đoàn</RouterLink></div><div v-if="loading" class="mt-7 flex justify-center text-slate-300"><LoaderCircle class="animate-spin" :size="21" /></div><p v-else-if="summary?.tour_count === 0" class="mt-7 rounded-xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400">Chưa có đoàn trong ngày hôm nay.</p></div>
  </div>
</template>
