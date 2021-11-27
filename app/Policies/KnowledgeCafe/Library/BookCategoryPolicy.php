<?php

namespace App\Policies\KnowledgeCafe\Library;

use Modules\User\Entities\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\KnowledgeCafe\Library\BookCategory;

class BookCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the book.
     *
     * @param  \Modules\User\Entities\User  $user
     * @param  \App\Models\KnowledgeCafe\Library\BookCategory $bookCategory
     * @return mixed
     */
    public function view(User $user, BookCategory $bookCategory)
    {
        return $user->hasPermissionTo('library_book_category.view');
    }

    /**
     * Determine whether the user can create books.
     *
     * @param  \Modules\User\Entities\User  $user
     * @return mixed
     */
    public function create(User $user, BookCategory $bookCategory)
    {
        return $user->hasPermissionTo('library_book_category.create');
    }

    /**
     * Determine whether the user can update the book.
     *
     * @param  \Modules\User\Entities\User  $user
     * @param  \App\Models\KnowledgeCafe\Library\BookCategory $bookCategory
     * @return mixed
     */
    public function update(User $user, BookCategory $bookCategory)
    {
        return  $user->hasPermissionTo('library_book_category.update');
    }

    /**
     * Determine whether the user can delete the book.
     *
     * @param  \Modules\User\Entities\User  $user
     * @param  \App\Models\KnowledgeCafe\Library\BookCategory $bookCategory
     * @return mixed
     */
    public function delete(User $user, BookCategory $bookCategory)
    {
        return $user->hasPermissionTo('library_book_category.delete');
    }

    /**
     * Determine whether the user can list books.
     *
     * @param  \Modules\User\Entities\User  $user
     * @return mixed
     */
    public function list(User $user, BookCategory $bookCategory)
    {
        return $user->hasPermissionTo('library_book_category.view');
    }

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('library_book_category.view');
    }
}
