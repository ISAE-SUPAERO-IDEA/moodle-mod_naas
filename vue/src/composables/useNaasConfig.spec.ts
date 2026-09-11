import { describe, it, expect } from 'vitest'
import { createApp } from 'vue'
import { useNaasConfig } from './useNaasConfig'

describe('useNaasConfig', () => {
  it('should throw an error if config is not provided', () => {
    const app = createApp({})
    expect(() => app.runWithContext(() => useNaasConfig())).toThrowError(/naasConfig not provided/)
  })

  it('should return the config if it is provided', () => {
    const app = createApp({})
    const mockConfig = { mount_point: '#test' }
    app.provide('naasConfig', mockConfig)
    const result = app.runWithContext(() => useNaasConfig())
    expect(result).toBe(mockConfig)
  })
})
