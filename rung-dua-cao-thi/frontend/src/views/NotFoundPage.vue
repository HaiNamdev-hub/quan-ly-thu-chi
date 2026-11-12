<template>
  <main class="flex min-h-dvh items-center justify-center bg-slate-50 px-4 py-10 text-center">
    <section class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
      <p class="text-sm font-semibold tracking-[0.2em] text-teal-700">404</p>
      <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Không tìm thấy trang</h1>
      <p class="mx-auto mt-3 max-w-sm text-sm leading-6 text-slate-500">Đường dẫn này không tồn tại hoặc đã được thay đổi.</p>
      <RouterLink class="mt-7 inline-flex min-h-11 items-center justify-center rounded-xl bg-teal-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-teal-800" to="/">Về trang chính</RouterLink>
    </section>
  </main>
</template>
