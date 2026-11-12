<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Boxes, CircleAlert, History, LoaderCircle, PackagePlus, Plus, X } from 'lucide-vue-next'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

interface Item { id: number; sku: string; name: string; unit: string; opening_quantity: number; current_quantity: number; low_stock_threshold: number; stock_control_enabled: boolean; is_active: boolean }
interface Movement { id: number; transaction_type: string; quantity_delta: number; notes: string; created_at: string; item?: { name: string; unit: string }; actor?: { name: string }; tour?: { code: string } }
const auth = useAuthStore()
const canManage = computed(() => auth.user?.role !== 'STAFF')
const items = ref<Item[]>([])
const movements = ref<Movement[]>([])
const loading = ref(true)
const errorMessage = ref('')
const showItemForm = ref(false)
const showMovementForm = ref(false)
const itemForm = reactive({ sku: '', name: '', unit: 'cái', opening_quantity: 0, low_stock_threshold: 0, stock_control_enabled: false })
const movementForm = reactive({ inventory_item_id: '', transaction_type: 'RECEIPT', quantity_delta: 1, notes: '' })
const lowStock = computed(() => items.value.filter((item) => item.current_quantity <= item.low_stock_threshold))

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await api.get<{ data: Item[] }>('/api/inventory')
    items.value = data.data
    if (canManage.value) {
      const history = await api.get<{ data: Movement[] }>('/api/inventory/transactions')
      movements.value = history.data.data.slice(0, 8)
    }
  } catch { errorMessage.value = 'Không tải được dữ liệu tồn kho.' }
  finally { loading.value = false }
}
onMounted(load)
async function createItem(): Promise<void> {
  try {
    await api.post('/api/inventory/items', itemForm)
    showItemForm.value = false
    Object.assign(itemForm, { sku: '', name: '', unit: 'cái', opening_quantity: 0, low_stock_threshold: 0, stock_control_enabled: false })
    await load()
  } catch { errorMessage.value = 'Không tạo được mặt hàng. SKU phải duy nhất.' }
}
async function createMovement(): Promise<void> {
  const delta = movementForm.transaction_type === 'ISSUE'
    ? -Math.abs(movementForm.quantity_delta)
    : movementForm.transaction_type === 'RECEIPT'
      ? Math.abs(movementForm.quantity_delta)
      : movementForm.quantity_delta
  try {
    await api.post('/api/inventory/transactions', { ...movementForm, inventory_item_id: Number(movementForm.inventory_item_id), quantity_delta: delta })
    showMovementForm.value = false
    await load()
  } catch { errorMessage.value = 'Không ghi nhận được biến động. Kiểm tra tồn kho và số lượng.' }
}
async function toggleControl(item: Item): Promise<void> {
  try { await api.put(`/api/inventory/items/${item.id}`, { stock_control_enabled: !item.stock_control_enabled }); await load() }
  catch { errorMessage.value = 'Không thể bật kiểm soát khi tồn kho đang âm.' }
}
function moneyQuantity(amount: number, unit: string): string { return `${new Intl.NumberFormat('vi-VN').format(amount)} ${unit}` }
function actionLabel(type: string): string { return ({ OPENING: 'Tồn đầu kỳ', RECEIPT: 'Nhập kho', ISSUE: 'Xuất kho', ADJUSTMENT: 'Điều chỉnh', TOUR_SALE: 'Xuất theo đoàn', TOUR_RETURN: 'Hoàn tồn đoàn' } as Record<string, string>)[type] ?? type }
</script>

<template>
  <div class="mx-auto min-w-0 max-w-7xl space-y-6"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Vật tư vận hành</p><h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Tồn kho</h1><p class="mt-1.5 text-sm text-slate-500">Ảnh bán sẽ tự tạo biến động với SKU được cấu hình trùng mã loại ảnh.</p></div><div v-if="canManage" class="grid w-full grid-cols-1 gap-2 min-[420px]:grid-cols-2 sm:w-auto"><button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700" @click="showMovementForm = true"><PackagePlus :size="16" />Nhập / điều chỉnh</button><button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white" @click="showItemForm = true"><Plus :size="16" />Thêm mặt hàng</button></div></div>
    <div v-if="errorMessage" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</div>
    <div v-if="lowStock.length" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"><CircleAlert :size="16" class="mt-0.5 shrink-0" /><span>Có {{ lowStock.length }} mặt hàng ở ngưỡng tồn thấp hoặc dưới ngưỡng.</span></div>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div class="flex items-center gap-2"><Boxes :size="17" class="text-emerald-800" /><h2 class="font-semibold text-slate-900">Danh mục tồn kho</h2></div><span class="text-xs text-slate-400">{{ items.length }} mặt hàng</span></div><div v-if="loading" class="py-14 text-center text-slate-400"><LoaderCircle class="mx-auto animate-spin" :size="20" /><span class="mt-2 block text-xs">Đang tải kho...</span></div><div v-else-if="!items.length" class="py-14 text-center"><Boxes class="mx-auto text-slate-300" :size="24" /><p class="mt-3 text-sm text-slate-500">Chưa cấu hình mặt hàng tồn kho.</p><p v-if="canManage" class="mt-1 text-xs text-slate-400">Thêm SKU large_frame, small_frame, large_plastic hoặc small_plastic để tự động theo dõi ảnh.</p></div><div v-else class="overflow-x-auto"><table class="w-full min-w-[780px] text-left text-sm"><thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3.5">Mặt hàng</th><th class="px-4 py-3.5">Tồn đầu kỳ</th><th class="px-4 py-3.5">Tồn hiện tại</th><th class="px-4 py-3.5">Ngưỡng thấp</th><th class="px-4 py-3.5">Kiểm soát tồn âm</th><th v-if="canManage" class="px-5 py-3.5 text-right">Thao tác</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="item in items" :key="item.id"><td class="px-5 py-4"><p class="font-medium text-slate-800">{{ item.name }}</p><p class="mt-1 font-mono text-[11px] text-slate-400">{{ item.sku }}</p></td><td class="px-4 py-4 text-xs text-slate-500">{{ moneyQuantity(item.opening_quantity, item.unit) }}</td><td class="px-4 py-4 text-sm font-semibold" :class="item.current_quantity <= item.low_stock_threshold ? 'text-amber-700' : 'text-slate-800'">{{ moneyQuantity(item.current_quantity, item.unit) }}</td><td class="px-4 py-4 text-xs text-slate-500">{{ moneyQuantity(item.low_stock_threshold, item.unit) }}</td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-medium" :class="item.stock_control_enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-500'">{{ item.stock_control_enabled ? 'Đang bật' : 'Đang tắt' }}</span></td><td v-if="canManage" class="px-5 py-4 text-right"><button class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-600 hover:bg-slate-50" @click="toggleControl(item)">{{ item.stock_control_enabled ? 'Tắt kiểm soát' : 'Bật kiểm soát' }}</button></td></tr></tbody></table></div></section>
    <section v-if="canManage" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div class="flex items-center gap-2"><History :size="16" class="text-emerald-800" /><h2 class="font-semibold text-slate-900">Biến động gần đây</h2></div><span class="text-xs text-slate-400">Tự động ghi nhật ký</span></div><div v-if="movements.length" class="divide-y divide-slate-100"><article v-for="movement in movements" :key="movement.id" class="flex min-w-0 flex-col justify-between gap-2 px-4 py-3 sm:flex-row sm:items-center sm:gap-3 sm:px-5"><div class="min-w-0"><p class="break-words text-sm font-medium text-slate-700">{{ movement.item?.name }} · {{ actionLabel(movement.transaction_type) }}</p><p class="mt-1 break-words text-xs text-slate-400">{{ movement.notes }}<span v-if="movement.tour"> · {{ movement.tour.code }}</span></p></div><div class="flex min-w-0 items-center justify-between gap-3 sm:block sm:shrink-0 sm:text-right"><p class="text-sm font-semibold" :class="movement.quantity_delta < 0 ? 'text-rose-700' : 'text-emerald-700'">{{ movement.quantity_delta > 0 ? '+' : '' }}{{ moneyQuantity(movement.quantity_delta, movement.item?.unit ?? '') }}</p><p class="text-[10px] text-slate-400 sm:mt-1">{{ movement.actor?.name }} · {{ new Date(movement.created_at).toLocaleDateString('vi-VN') }}</p></div></article></div><p v-else class="py-10 text-center text-sm text-slate-400">Chưa có biến động kho.</p></section>
    <div v-if="showItemForm" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="showItemForm = false"><section class="mobile-modal-panel w-full max-w-lg rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Danh mục vật tư</p><h2 class="mt-1 text-xl font-semibold text-slate-900">Thêm mặt hàng</h2></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="showItemForm = false"><X :size="18" /></button></div><form class="mt-5 space-y-4" @submit.prevent="createItem"><label class="field-label">SKU<input v-model="itemForm.sku" required placeholder="large_frame" class="form-control" /></label><label class="field-label">Tên mặt hàng<input v-model="itemForm.name" required class="form-control" /></label><div class="grid grid-cols-2 gap-3"><label class="field-label">Đơn vị<input v-model="itemForm.unit" required class="form-control" /></label><label class="field-label">Tồn đầu kỳ<input v-model.number="itemForm.opening_quantity" min="0" required type="number" class="form-control" /></label><label class="field-label">Cảnh báo tồn thấp<input v-model.number="itemForm.low_stock_threshold" min="0" required type="number" class="form-control" /></label><label class="mt-6 flex items-center gap-2 text-xs text-slate-600"><input v-model="itemForm.stock_control_enabled" type="checkbox" class="accent-emerald-800" />Chặn xuất vượt tồn</label></div><div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="showItemForm = false">Hủy</button><button class="rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white">Tạo mặt hàng</button></div></form></section></div>
    <div v-if="showMovementForm" class="mobile-modal fixed inset-0 z-50 flex items-end justify-center bg-slate-950/40 p-0 sm:items-center sm:p-4" @click.self="showMovementForm = false"><section class="mobile-modal-panel w-full max-w-lg rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Phiếu kho</p><h2 class="mt-1 text-xl font-semibold text-slate-900">Nhập hoặc điều chỉnh</h2></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" @click="showMovementForm = false"><X :size="18" /></button></div><form class="mt-5 space-y-4" @submit.prevent="createMovement"><label class="field-label">Mặt hàng<select v-model="movementForm.inventory_item_id" required class="form-control"><option value="">Chọn mặt hàng</option><option v-for="item in items" :key="item.id" :value="String(item.id)">{{ item.name }} · tồn {{ item.current_quantity }} {{ item.unit }}</option></select></label><label class="field-label">Loại phiếu<select v-model="movementForm.transaction_type" class="form-control"><option value="RECEIPT">Nhập kho</option><option value="ISSUE">Xuất kho</option><option value="ADJUSTMENT">Điều chỉnh (+/-)</option></select></label><label class="field-label">Số lượng<input v-model.number="movementForm.quantity_delta" required :min="movementForm.transaction_type === 'ADJUSTMENT' ? undefined : 1" type="number" class="form-control" /><span class="text-[11px] font-normal text-slate-400">Với điều chỉnh, số dương tăng kho và số âm giảm kho.</span></label><label class="field-label">Lý do<input v-model="movementForm.notes" required minlength="3" class="form-control" /></label><div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600" @click="showMovementForm = false">Hủy</button><button class="rounded-xl bg-[#174b3e] px-4 py-2.5 text-sm font-medium text-white">Ghi nhận</button></div></form></section></div>
  </div>
</template>
