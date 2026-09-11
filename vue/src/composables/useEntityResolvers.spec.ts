import { describe, it, expect, vi } from 'vitest'
import { createApp } from 'vue'
import { useEntityResolvers } from './useEntityResolvers'
import { NAAS_API_KEY } from '@/plugins/naas-api.plugin'

function withProviders<T>(service: object, run: () => T): T {
  const app = createApp({})
  app.provide(NAAS_API_KEY, service)
  app.provide('naasConfig', { courseId: 7 })
  return app.runWithContext(run)
}

describe('useEntityResolvers', () => {
  it('builds a person name from snake_case fields', async () => {
    const getPerson = vi.fn().mockResolvedValue({ first_name: 'Ada', last_name: 'Lovelace' })
    const { getPersonName } = withProviders({ getPerson }, () => useEntityResolvers())
    await expect(getPersonName('hash-1')).resolves.toBe('ADA LOVELACE')
  })

  it('uses structure acronym then name', async () => {
    const getStructure = vi.fn().mockResolvedValue({ name: 'ISAE-SUPAERO' })
    const { getStructureAcronym } = withProviders({ getStructure }, () => useEntityResolvers())
    await expect(getStructureAcronym('hash-2')).resolves.toBe('ISAE-SUPAERO')
  })
})
