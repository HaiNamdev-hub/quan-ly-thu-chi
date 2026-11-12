export type UserRole = 'ADMIN' | 'MANAGER' | 'STAFF'

export interface AuthUser {
  id: number
  code: string
  name: string
  username: string | null
  email: string
  phone: string | null
  role: UserRole
}
