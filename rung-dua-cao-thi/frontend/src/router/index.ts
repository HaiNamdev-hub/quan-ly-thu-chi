import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { pinia } from '../stores/pinia'
import LoginPage from '../views/auth/LoginPage.vue'
import ManagementLayout from '../layouts/ManagementLayout.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: LoginPage, meta: { guestOnly: true } },
    { path: '/403', component: () => import('../views/ForbiddenPage.vue') },
    {
      path: '/', component: ManagementLayout, meta: { requiresAuth: true },
      children: [
        { path: '', redirect: () => '/dashboard' },
        { path: 'dashboard', component: () => import('../views/dashboard/DashboardPage.vue'), meta: { roles: ['ADMIN', 'MANAGER'] } },
        { path: 'operations/today', component: () => import('../views/dashboard/OperationsPage.vue'), meta: { roles: ['STAFF'] } },
        { path: 'admin/users', component: () => import('../views/admin/UsersPage.vue'), meta: { roles: ['ADMIN'] } },
        { path: 'admin/import-excel', component: () => import('../views/admin/ExcelImportPage.vue'), meta: { roles: ['ADMIN'] } },
        { path: 'tours', component: () => import('../views/tours/ToursPage.vue'), meta: { roles: ['ADMIN', 'MANAGER', 'STAFF'] } },
        { path: 'guides', component: () => import('../views/guides/GuidesPage.vue'), meta: { roles: ['ADMIN', 'MANAGER', 'STAFF'] } },
        { path: 'expenses', component: () => import('../views/expenses/ExpensesPage.vue'), meta: { roles: ['ADMIN', 'MANAGER'] } },
        { path: 'inventory', component: () => import('../views/inventory/InventoryPage.vue'), meta: { roles: ['ADMIN', 'MANAGER', 'STAFF'] } },
        { path: 'photos', component: () => import('../views/photos/PhotosPage.vue'), meta: { roles: ['ADMIN', 'MANAGER', 'STAFF'] } },
      ],
    },
    { path: '/:pathMatch(.*)*', component: () => import('../views/NotFoundPage.vue') },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore(pinia)
  if (!auth.initialized) await auth.fetchUser()

  if (to.meta.requiresAuth && !auth.user) return '/login'
  if (to.meta.guestOnly && auth.user) {
    return auth.user.role === 'STAFF' ? '/operations/today' : '/dashboard'
  }
  const roles = to.meta.roles as string[] | undefined
  if (roles && auth.user && !roles.includes(auth.user.role)) return '/403'
})

export default router
