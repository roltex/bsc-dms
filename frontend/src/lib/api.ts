import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default api

export async function getCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', {
    baseURL: '',
    withCredentials: true,
  })
}
