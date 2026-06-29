import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, clearAuthToken, hasAuthToken, setAuthToken, useApi } from '../composables/useApi'

interface MockResponse {
  ok?: boolean
  status?: number
  jsonData?: unknown
}

function mockFetch(response: MockResponse = {}) {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: response.ok ?? true,
    status: response.status ?? 200,
    json: async () => response.jsonData ?? {},
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

describe('useApi', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('GET returns parsed JSON and prefixes the /api base path', async () => {
    const fetchMock = mockFetch({ jsonData: { hello: 'world' } })
    const result = await useApi().get<{ hello: string }>('/test/')

    expect(result).toEqual({ hello: 'world' })
    expect(fetchMock).toHaveBeenCalledWith('/api/test/', expect.any(Object))
  })

  it('POST sends a JSON body with the right method and content type', async () => {
    const fetchMock = mockFetch({ jsonData: { id: 1 } })
    await useApi().post('/create/', { name: 'x' })

    const [, opts] = fetchMock.mock.calls[0]
    expect(opts.method).toBe('POST')
    expect(opts.body).toBe(JSON.stringify({ name: 'x' }))
    expect((opts.headers as Record<string, string>)['Content-Type']).toBe('application/json')
  })

  it('attaches the bearer token when one is stored', async () => {
    setAuthToken('tok123')
    const fetchMock = mockFetch()
    await useApi().get('/protected/')

    const [, opts] = fetchMock.mock.calls[0]
    expect((opts.headers as Headers).get('Authorization')).toBe('Bearer tok123')
  })

  it('throws a structured ApiError that reads the JSON error body', async () => {
    mockFetch({ ok: false, status: 404, jsonData: { error: 'MediaNotFound', message: 'no media' } })

    await expect(useApi().get('/missing/')).rejects.toMatchObject({
      name: 'ApiError',
      status: 404,
      code: 'MediaNotFound',
      message: 'no media',
    })
  })

  it('falls back to a status-based message when the body has none', async () => {
    mockFetch({ ok: false, status: 403, jsonData: {} })

    await expect(useApi().get('/forbidden/')).rejects.toMatchObject({
      status: 403,
      message: 'You do not have permission to perform this action.',
    })
  })

  it('clears the auth token on a 401 response', async () => {
    setAuthToken('tok')
    expect(hasAuthToken()).toBe(true)
    mockFetch({ ok: false, status: 401 })

    await expect(useApi().get('/protected/')).rejects.toBeInstanceOf(ApiError)
    expect(hasAuthToken()).toBe(false)
  })

  it('token helpers set, check, and clear sessionStorage', () => {
    expect(hasAuthToken()).toBe(false)
    setAuthToken('abc')
    expect(hasAuthToken()).toBe(true)
    clearAuthToken()
    expect(hasAuthToken()).toBe(false)
  })
})
