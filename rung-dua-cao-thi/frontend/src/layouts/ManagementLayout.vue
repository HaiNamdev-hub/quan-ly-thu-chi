<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Waves, LayoutDashboard, UsersRound, CalendarDays, LogOut, ShieldCheck, Compass,
  ContactRound, ReceiptText, Boxes, Image, Menu, X, FileSpreadsheet,
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const isAdmin = computed(() => auth.user?.role === 'ADMIN')
const isStaff = computed(() => auth.user?.role === 'STAFF')
const drawerOpen = ref(false)
const pageTitle = computed(() => {
  const currentPath = route.path
  if (currentPath.startsWith('/admin/users')) return 'Tài khoản nhân sự'
  if (currentPath.startsWith('/admin/import-excel')) return 'Import Excel lịch sử'
  if (currentPath.startsWith('/operations')) return 'Vận hành hôm nay'
  if (currentPath.startsWith('/dashboard')) return 'Tổng quan kinh doanh'
  if (currentPath.startsWith('/tours')) return 'Quản lý đoàn khách'
  if (currentPath.startsWith('/guides')) return 'Hướng dẫn viên'
  if (currentPath.startsWith('/inventory')) return 'Tồn kho'
  if (currentPath.startsWith('/photos')) return 'Ảnh bán'
  if (currentPath.startsWith('/expenses')) return 'Thu chi'
  return 'CAO THI MANAGEMENT'
})

let previousBodyOverflow = ''
watch(drawerOpen, (isOpen) => {
  if (typeof document === 'undefined') return
  if (isOpen) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = previousBodyOverflow
  }
})
watch(() => route.fullPath, () => { drawerOpen.value = false })

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') drawerOpen.value = false
}

function onResize(): void {
  if (window.matchMedia('(min-width: 1024px)').matches) drawerOpen.value = false
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('resize', onResize)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', onResize)
  document.body.style.overflow = previousBodyOverflow
})

async function signOut(): Promise<void> {
  drawerOpen.value = false
  await auth.logout()
  await router.replace('/login')
}

function roleLabel(): string {
  return auth.user?.role === 'ADMIN' ? 'Quản trị viên' : auth.user?.role === 'MANAGER' ? 'Quản lý' : 'Nhân viên'
}
</script>

<template>
  <div class="min-h-dvh overflow-x-clip bg-[#f6f8f6] text-slate-800 lg:pl-[258px]">
    <div v-if="drawerOpen" class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[1px] lg:hidden" aria-hidden="true" @click="drawerOpen = false" />

    <aside
      id="management-navigation"
      class="fixed inset-y-0 left-0 z-50 flex w-[min(20rem,86vw)] -translate-x-full flex-col bg-[#123f35] pt-[env(safe-area-inset-top)] text-white shadow-2xl transition-transform duration-200 ease-out lg:w-[258px] lg:translate-x-0 lg:shadow-none"
      :class="{ 'translate-x-0': drawerOpen }"
    >
      <div class="flex items-center justify-between gap-3 px-5 py-5 sm:px-6 lg:py-6">
        <div class="flex min-w-0 items-center gap-3">
          <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10"><Waves :size="21" /></div>
          <div class="min-w-0"><p class="truncate text-xs font-bold tracking-[0.18em]">CAO THỊ</p><p class="mt-0.5 text-[10px] tracking-[0.12em] text-white/55">MANAGEMENT</p></div>
        </div>
        <button class="flex size-11 shrink-0 items-center justify-center rounded-xl text-white/75 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white lg:hidden" aria-label="Đóng menu" @click="drawerOpen = false"><X :size="20" /></button>
      </div>

      <div class="hidden px-4 pb-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40 lg:block">Không gian làm việc</div>
      <nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 pb-4" aria-label="Điều hướng chính">
        <RouterLink v-if="!isStaff" to="/dashboard" class="side-link" @click="drawerOpen = false"><LayoutDashboard :size="18" />Tổng quan</RouterLink>
        <RouterLink v-if="isStaff" to="/operations/today" class="side-link" @click="drawerOpen = false"><CalendarDays :size="18" />Vận hành hôm nay</RouterLink>
        <RouterLink to="/tours" class="side-link" @click="drawerOpen = false"><Compass :size="18" />Quản lý đoàn</RouterLink>
        <RouterLink to="/guides" class="side-link" @click="drawerOpen = false"><ContactRound :size="18" />Hướng dẫn viên</RouterLink>
        <RouterLink to="/inventory" class="side-link" @click="drawerOpen = false"><Boxes :size="18" />Tồn kho</RouterLink>
        <RouterLink to="/photos" class="side-link" @click="drawerOpen = false"><Image :size="18" />Ảnh bán</RouterLink>
        <RouterLink v-if="!isStaff" to="/expenses" class="side-link" @click="drawerOpen = false"><ReceiptText :size="18" />Thu chi</RouterLink>
        <RouterLink v-if="isAdmin" to="/admin/users" class="side-link" @click="drawerOpen = false"><UsersRound :size="18" />Tài khoản nhân sự</RouterLink>
        <RouterLink v-if="isAdmin" to="/admin/import-excel" class="side-link" @click="drawerOpen = false"><FileSpreadsheet :size="18" />Import Excel lịch sử</RouterLink>
      </nav>

      <div class="border-t border-white/10 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
        <div class="mb-3 flex min-w-0 items-center gap-3 rounded-xl bg-white/[0.07] p-3">
          <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#d9eadd] text-xs font-bold text-[#245844]">{{ auth.user?.name.slice(0, 1).toUpperCase() }}</div>
          <div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">{{ auth.user?.name }}</p><p class="mt-0.5 text-[10px] text-white/55">{{ roleLabel() }}</p></div>
          <ShieldCheck :size="16" class="shrink-0 text-emerald-200" />
        </div>
        <button class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/70 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white" @click="signOut"><LogOut :size="16" />Đăng xuất</button>
      </div>
    </aside>

    <div class="flex min-h-dvh min-w-0 flex-col" :inert="drawerOpen">
      <header class="sticky top-0 z-30 flex min-h-14 items-center justify-between gap-3 border-b border-slate-200/80 bg-white/95 px-3 pt-[env(safe-area-inset-top)] backdrop-blur sm:px-5 lg:static lg:min-h-[76px] lg:px-8 lg:pt-0">
        <div class="flex min-w-0 items-center gap-3">
          <button class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 lg:hidden" type="button" aria-controls="management-navigation" :aria-expanded="drawerOpen" aria-label="Mở menu" @click="drawerOpen = true"><Menu :size="20" /></button>
          <div class="min-w-0 lg:hidden"><p class="truncate text-xs font-bold tracking-[0.12em] text-[#174b3e]">CAO THỊ</p><p class="truncate text-[11px] text-slate-500">{{ pageTitle }}</p></div>
          <div class="hidden min-w-0 lg:block"><p class="text-xs text-slate-400">Rừng Dừa Cao Thị <span class="px-1.5">/</span><span class="text-slate-700">{{ pageTitle }}</span></p></div>
        </div>
        <div class="hidden shrink-0 items-center gap-5 lg:flex">
          <div class="flex items-center gap-2 text-xs text-slate-500"><span class="size-2 rounded-full bg-emerald-500" />Hệ thống hoạt động</div>
          <div class="flex min-w-0 items-center gap-2 border-l border-slate-200 pl-5">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-900">{{ auth.user?.name.slice(0, 1).toUpperCase() }}</div>
            <div class="max-w-44 min-w-0"><p class="truncate text-xs font-medium text-slate-700">{{ auth.user?.name }}</p><p class="text-[10px] text-slate-400">{{ roleLabel() }}</p></div>
          </div>
          <button class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-700" @click="signOut"><LogOut :size="15" />Đăng xuất</button>
        </div>
        <span class="flex shrink-0 items-center gap-1.5 text-[10px] text-slate-500 sm:text-xs lg:hidden"><span class="size-2 rounded-full bg-emerald-500" />Đang hoạt động</span>
      </header>

      <main class="min-w-0 flex-1 px-3 py-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:px-5 sm:py-6 lg:px-8 lg:py-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>
