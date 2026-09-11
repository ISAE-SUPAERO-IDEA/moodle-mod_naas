import { describe, it, expect } from 'vitest'
import { createApp } from 'vue'
import { useMoodleService } from './useMoodleService'
import { NAAS_API_KEY } from '@/plugins/naas-api.plugin'

describe('useMoodleService', () => {
  it('should throw an error if service is not provided', () => {
    const app = createApp({})
    expect(() => app.runWithContext(() => useMoodleService())).toThrowError(/naasApi not provided/)
  })

  it('should return the service if it is provided', () => {
    const app = createApp({})
    const mockService = { getVersion: () => Promise.resolve() }
    app.provide(NAAS_API_KEY, mockService)
    const result = app.runWithContext(() => useMoodleService())
    expect(result).toBe(mockService)
  })
})
