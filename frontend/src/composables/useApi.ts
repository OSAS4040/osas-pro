import apiClient from '@/lib/apiClient'

export function useApi() {
  async function get(path: string, params?: Record<string, any>) {
    const { data } = await apiClient.get(path, { params })
    return data
  }

  async function post(path: string, body?: any) {
    const { data } = await apiClient.post(path, body)
    return data
  }

  async function put(path: string, body?: any) {
    const { data } = await apiClient.put(path, body)
    return data
  }

  async function del(path: string) {
    const { data } = await apiClient.delete(path)
    return data
  }

  async function upload(path: string, formData: FormData) {
    const { data } = await apiClient.post(path, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  }

  return { get, post, put, del, upload }
}
