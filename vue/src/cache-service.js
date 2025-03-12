const cache = new Map();


export  default {
    has: (key) => {
        return cache.has(JSON.stringify(key))
    },
    get: (key) => {
        return cache.get(JSON.stringify(key))
    },
    set: (key, value) => {
        return cache.set(JSON.stringify(key), value)
    }
}