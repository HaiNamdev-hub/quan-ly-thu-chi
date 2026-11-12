<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, EyeOff, LockKeyhole, UserRound, Waves } from 'lucide-vue-next'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const login = ref('')
const password = ref('')
const remember = ref(false)
const showPassword = ref(false)
const loading = ref(false)
const errorMessage = ref('')
const inputType = computed(() => showPassword.value ? 'text' : 'password')

async function submit(): Promise<void> {
  errorMessage.value = ''
  loading.value = true
  try {
    const user = await auth.login({ login: login.value.trim(), password: password.value, remember: remember.value })
    await router.replace(user.role === 'STAFF' ? '/operations/today' : '/dashboard')
  } catch (error: unknown) {
    if (axios.isAxiosError(error) && error.response?.status === 429) {
      errorMessage.value = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng chờ một phút rồi thử lại.'
    } else {
      errorMessage.value = 'Tên đăng nhập hoặc mật khẩu không chính xác.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-page min-h-dvh bg-[#f5f7f4] text-slate-800 lg:grid lg:grid-cols-[1.05fr_0.95fr]">
    <section class="login-story relative hidden overflow-hidden bg-[#123f35] px-14 py-12 text-white lg:flex lg:flex-col lg:justify-between xl:px-20">
      <div class="story-glow story-glow-one"></div>
      <div class="story-glow story-glow-two"></div>
      <div class="relative z-10 flex items-center gap-3">
        <div class="flex size-12 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20"><Waves :size="25" /></div>
        <div><p class="text-sm font-semibold tracking-[0.24em] text-emerald-100">RỪNG DỪA</p><p class="text-xs tracking-[0.2em] text-white/65">CAO THỊ</p></div>
      </div>
      <div class="relative z-10 max-w-xl pb-12">
        <p class="mb-5 text-sm font-semibold uppercase tracking-[0.22em] text-emerald-200">Quản trị vận hành</p>
        <h1 class="max-w-lg text-5xl font-semibold leading-[1.12] tracking-tight xl:text-6xl">Mỗi chuyến đi,<br />một trải nghiệm<br /><span class="text-emerald-200">đáng nhớ.</span></h1>
        <p class="mt-7 max-w-md text-base leading-7 text-emerald-50/75">Không gian quản lý tập trung cho hoạt động tour thuyền thúng Rừng Dừa Cao Thị.</p>
      </div>
      <div class="relative z-10 flex items-center justify-between border-t border-white/15 pt-5 text-xs text-white/55">
        <span>CAO THI MANAGEMENT SYSTEM</span><span>HỘI AN · VIỆT NAM</span>
      </div>
      <div class="palm-mark" aria-hidden="true">✳</div>
    </section>

    <section class="flex min-h-dvh min-w-0 items-center justify-center px-4 py-8 sm:px-10 sm:py-10 lg:px-12">
      <div class="w-full max-w-[430px]">
        <div class="mb-10 flex items-center gap-3 lg:hidden">
          <div class="flex size-11 items-center justify-center rounded-2xl bg-[#123f35] text-white"><Waves :size="22" /></div>
          <div><p class="text-sm font-bold tracking-[0.16em] text-[#123f35]">CAO THỊ</p><p class="text-[11px] tracking-[0.12em] text-slate-500">RỪNG DỪA · HỘI AN</p></div>
        </div>
        <div class="mb-9">
          <p class="mb-2 text-xs font-bold uppercase tracking-[0.2em] text-[#55816c]">CAO THI MANAGEMENT</p>
          <h2 class="text-3xl font-semibold tracking-tight text-slate-900">Đăng nhập hệ thống</h2>
          <p class="mt-2 text-sm text-slate-500">Nhập thông tin tài khoản được cấp để tiếp tục.</p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
          <div>
            <label for="login" class="mb-2 block text-sm font-medium text-slate-700">Tên đăng nhập hoặc email</label>
            <div class="relative">
              <UserRound class="field-icon" :size="18" />
              <input id="login" v-model="login" autocomplete="username" required maxlength="255" class="login-input" placeholder="Nhập tên đăng nhập hoặc email" />
            </div>
          </div>
          <div>
            <div class="mb-2 flex items-center justify-between"><label for="password" class="text-sm font-medium text-slate-700">Mật khẩu</label></div>
            <div class="relative">
              <LockKeyhole class="field-icon" :size="18" />
              <input id="password" v-model="password" :type="inputType" autocomplete="current-password" required maxlength="255" class="login-input pr-12" placeholder="Nhập mật khẩu" />
              <button type="button" class="password-toggle" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" :size="18" /><Eye v-else :size="18" /></button>
            </div>
          </div>
          <div class="flex items-center">
            <input id="remember" v-model="remember" type="checkbox" class="size-4 rounded border-slate-300 accent-[#236b52]" />
            <label for="remember" class="ml-2.5 text-sm text-slate-600">Ghi nhớ đăng nhập</label>
          </div>

          <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-5 text-rose-700">{{ errorMessage }}</div>
          <button type="submit" :disabled="loading" class="login-submit">
            <span v-if="loading" class="loading-spinner" aria-hidden="true"></span>
            {{ loading ? 'Đang xác thực...' : 'Đăng nhập' }}
          </button>
        </form>

        <div class="mt-9 rounded-2xl border border-[#e4ebe5] bg-white/80 p-4">
          <div class="flex gap-3"><div class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#edf5ef] text-[#387455]"><LockKeyhole :size="16" /></div><p class="text-xs leading-5 text-slate-500">Tài khoản được quản trị viên cấp. Nếu bạn chưa có quyền truy cập, vui lòng liên hệ người quản trị hệ thống.</p></div>
        </div>
        <p class="mt-8 text-center text-xs text-slate-400">© {{ new Date().getFullYear() }} Rừng Dừa Cao Thị · Hội An</p>
      </div>
    </section>
  </main>
</template>
