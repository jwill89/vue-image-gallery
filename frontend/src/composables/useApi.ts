const API_BASE_URL = '/api'

/**
 * Get the stored auth token for protected endpoints.
 */
function getAuthToken(): string | null {
  return sessionStorage.getItem('auth_token')
}

export function setAuthToken(token: string): void {
  sessionStorage.setItem('auth_token', token)
}

export function clearAuthToken(): void {
  sessionStorage.removeItem('auth_token')
}

export function hasAuthToken(): boolean {
  return !!sessionStorage.getItem('auth_token')
}

export function useApi() {
  async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    // Attach auth token if available
    const token = getAuthToken()
    if (token) {
      const headers = new Headers(options.headers)
      if (!headers.has('Authorization')) {
        headers.set('Authorization', `Bearer ${token}`)
      }
      options.headers = headers
    }

    const response = await fetch(`${API_BASE_URL}${url}`, options)
    if (!response.ok) {
      if (response.status === 401) {
        clearAuthToken()
      }
      throw new Error(`HTTP ${response.status} for ${url}`)
    }
    return response.json() as Promise<T>
  }

  function get<T>(url: string): Promise<T> {
    return request<T>(url)
  }

  function post<T>(url: string, body: unknown): Promise<T> {
    return request<T>(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
  }

  function put<T>(url: string, body: unknown): Promise<T> {
    return request<T>(url, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
  }

  function patch<T>(url: string, body: unknown): Promise<T> {
    return request<T>(url, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
  }

  function del<T>(url: string, body?: unknown): Promise<T> {
    return request<T>(url, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined
    })
  }

  return { get, post, put, patch, del }
}
