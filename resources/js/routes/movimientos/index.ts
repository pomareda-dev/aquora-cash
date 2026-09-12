import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:21
* @route '/movimientos'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/movimientos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:21
* @route '/movimientos'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:21
* @route '/movimientos'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:21
* @route '/movimientos'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:21
* @route '/movimientos'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:21
* @route '/movimientos'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:21
* @route '/movimientos'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:133
* @route '/movimientos'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/movimientos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:133
* @route '/movimientos'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:133
* @route '/movimientos'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:133
* @route '/movimientos'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:133
* @route '/movimientos'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:209
* @route '/movimientos/reorder'
*/
export const reorder = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reorder.url(options),
    method: 'patch',
})

reorder.definition = {
    methods: ["patch"],
    url: '/movimientos/reorder',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:209
* @route '/movimientos/reorder'
*/
reorder.url = (options?: RouteQueryOptions) => {
    return reorder.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:209
* @route '/movimientos/reorder'
*/
reorder.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reorder.url(options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:209
* @route '/movimientos/reorder'
*/
const reorderForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorder.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:209
* @route '/movimientos/reorder'
*/
reorderForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reorder.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

reorder.form = reorderForm

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
export const update = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/movimientos/{movement}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
update.url = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { movement: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { movement: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            movement: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        movement: typeof args.movement === 'object'
        ? args.movement.id
        : args.movement,
    }

    return update.definition.url
            .replace('{movement}', parsedArgs.movement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
update.put = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
const updateForm = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
updateForm.put = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
export const patch = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: patch.url(args, options),
    method: 'patch',
})

patch.definition = {
    methods: ["patch"],
    url: '/movimientos/{movement}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
patch.url = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { movement: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { movement: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            movement: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        movement: typeof args.movement === 'object'
        ? args.movement.id
        : args.movement,
    }

    return patch.definition.url
            .replace('{movement}', parsedArgs.movement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
patch.patch = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: patch.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
const patchForm = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: patch.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:157
* @route '/movimientos/{movement}'
*/
patchForm.patch = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: patch.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

patch.form = patchForm

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:190
* @route '/movimientos/{movement}'
*/
export const destroy = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/movimientos/{movement}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:190
* @route '/movimientos/{movement}'
*/
destroy.url = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { movement: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { movement: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            movement: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        movement: typeof args.movement === 'object'
        ? args.movement.id
        : args.movement,
    }

    return destroy.definition.url
            .replace('{movement}', parsedArgs.movement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:190
* @route '/movimientos/{movement}'
*/
destroy.delete = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:190
* @route '/movimientos/{movement}'
*/
const destroyForm = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:190
* @route '/movimientos/{movement}'
*/
destroyForm.delete = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const movimientos = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    reorder: Object.assign(reorder, reorder),
    update: Object.assign(update, update),
    patch: Object.assign(patch, patch),
    destroy: Object.assign(destroy, destroy),
}

export default movimientos