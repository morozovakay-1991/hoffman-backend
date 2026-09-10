<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/articles')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/articles')->assertForbidden();
    }

    public function test_an_admin_can_access_and_search_articles(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Article::factory()->create(['title' => ['ru' => 'Как начать медитировать']]);
        $second = Article::factory()->create(['title' => ['ru' => 'Основы осознанности']]);

        $this->actingAs($admin)->get('/backend/articles')->assertSuccessful();

        Livewire::test(ListArticles::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->searchTable('медитировать')
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);
    }

    public function test_a_super_admin_can_access_articles(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get('/backend/articles')->assertSuccessful();
    }

    public function test_an_admin_can_toggle_the_is_new_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->create(['is_new' => false, 'cover_image_path' => null]);

        $this->actingAs($admin);

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['is_new' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($article->refresh()->is_new);

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->fillForm(['is_new' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($article->refresh()->is_new);
    }
}
