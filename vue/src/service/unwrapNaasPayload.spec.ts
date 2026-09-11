import { describe, expect, it } from 'vitest'
import { unwrapNaasPayload } from './unwrapNaasPayload'

describe('unwrapNaasPayload', () => {
  it('reads payload from a wrapped JSON string', () => {
    expect(unwrapNaasPayload('{"payload":{"firstname":"Ada"}}')).toEqual({ firstname: 'Ada' })
  })

  it('reads a bare entity JSON string', () => {
    expect(unwrapNaasPayload('{"firstname":"Ada","lastname":"Lovelace"}')).toEqual({
      firstname: 'Ada',
      lastname: 'Lovelace',
    })
  })

  it('reads payload from an already-parsed object', () => {
    expect(unwrapNaasPayload({ payload: { acronym: 'ISAE' } })).toEqual({ acronym: 'ISAE' })
  })
})
