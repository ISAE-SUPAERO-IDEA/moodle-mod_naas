import { describe, it, expect, vi } from 'vitest'
import { createApp } from 'vue'
import { useNuggetSearch } from './useNuggetSearch'
import { NAAS_API_KEY } from '@/plugins/naas-api.plugin'
import type { Nugget } from '@/types/nugget.types'

const nugget = {
  nugget_id: 'n1',
  name: 'Alpha',
  resume: 'Intro',
  authors: ['a1'],
  domains: ['d1'],
} as Nugget

function withProviders<T>(service: object, run: () => T): T {
  const app = createApp({})
  app.provide(NAAS_API_KEY, service)
  app.provide('naasConfig', { courseId: 7 })
  return app.runWithContext(run)
}

describe('useNuggetSearch', () => {
  it('paints search hits before author and domain enrichment resolves', async () => {
    let resolvePerson!: (value: unknown) => void
    const personPromise = new Promise((resolve) => {
      resolvePerson = resolve
    })
    const searchNuggets = vi.fn().mockResolvedValue({
      items: [nugget],
      results_count: 1,
      aggregations: { level: { buckets: [] } },
    })
    const getPerson = vi.fn().mockReturnValue(personPromise)
    const getDomain = vi.fn().mockResolvedValue({ id: 'd1', label: 'Math' })

    const { nuggets, loading, search } = withProviders(
      { searchNuggets, getPerson, getDomain },
      () => useNuggetSearch()
    )

    const result = await search({ page_size: 9 })

    expect(loading.value).toBe(false)
    expect(nuggets.value).toHaveLength(1)
    expect(nuggets.value[0].name).toBe('Alpha')
    expect(nuggets.value[0].authors_data).toBeUndefined()
    expect(result?.aggregations.level).toEqual({ buckets: [] })

    resolvePerson({ firstname: 'Ada', lastname: 'Lovelace', email: 'ada@example.com' })
    await vi.waitFor(() => {
      expect(nuggets.value[0].authors_data?.[0]).toMatchObject({ firstname: 'Ada' })
    })
    expect(nuggets.value[0].domains_data?.[0]).toMatchObject({ label: 'Math' })
  })

  it('does not apply enrichment from a superseded search', async () => {
    let resolveFirstPerson!: (value: unknown) => void
    const firstPerson = new Promise((resolve) => {
      resolveFirstPerson = resolve
    })
    const searchNuggets = vi.fn()
      .mockResolvedValueOnce({
        items: [{ ...nugget, nugget_id: 'old', name: 'Old' }],
        results_count: 1,
        aggregations: {},
      })
      .mockResolvedValueOnce({
        items: [{ ...nugget, nugget_id: 'new', name: 'New', authors: [] }],
        results_count: 1,
        aggregations: {},
      })
    const getPerson = vi.fn()
      .mockReturnValueOnce(firstPerson)
      .mockResolvedValue({ firstname: 'Ignored', lastname: 'Person', email: 'x@example.com' })
    const getDomain = vi.fn().mockResolvedValue({ id: 'd1', label: 'Math' })

    const { nuggets, search } = withProviders(
      { searchNuggets, getPerson, getDomain },
      () => useNuggetSearch()
    )

    await search({ page_size: 9, fulltext: 'old' })
    expect(nuggets.value[0].nugget_id).toBe('old')

    await search({ page_size: 9, fulltext: 'new' })
    expect(nuggets.value[0].nugget_id).toBe('new')

    resolveFirstPerson({ firstname: 'Stale', lastname: 'Author', email: 'stale@example.com' })
    await new Promise((resolve) => setTimeout(resolve, 20))
    expect(nuggets.value[0].nugget_id).toBe('new')
    expect(nuggets.value[0].authors_data?.some((author) => author.firstname === 'Stale')).toBeFalsy()
  })
})
