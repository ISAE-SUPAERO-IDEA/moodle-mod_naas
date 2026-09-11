import { describe, it, expect } from 'vitest'
import { useComponentId } from './useComponentId'

describe('useComponentId', () => {
  it('should generate unique prefixes for each component', () => {
    const getId1 = useComponentId()
    const getId2 = useComponentId()

    const id1 = getId1('button')
    const id2 = getId2('button')

    expect(id1).not.toBe(id2)
    expect(id1).toMatch(/^naas-\d+-button$/)
  })

  it('should append the suffix correctly', () => {
    const getId = useComponentId()
    expect(getId('test')).toMatch(/^naas-\d+-test$/)
  })
})
