import { Routes } from '@angular/router';
import { authGuard } from './core/auth/auth.guard';

const comingSoon = () => import('./features/coming-soon/coming-soon.page').then((m) => m.ComingSoonPage);

export const routes: Routes = [
  {
    path: '',
    title: 'la Plateforme · Petites annonces près de chez vous',
    loadComponent: () => import('./features/home/home.page').then((m) => m.HomePage),
  },
  {
    path: 'connexion',
    title: 'Connexion · la Plateforme',
    loadComponent: () => import('./features/auth/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'inscription',
    title: 'Créer un compte · la Plateforme',
    loadComponent: () => import('./features/auth/register.page').then((m) => m.RegisterPage),
  },
  {
    path: 'deposer',
    title: 'Déposer une annonce · la Plateforme',
    canActivate: [authGuard],
    loadComponent: () => import('./features/deposit/deposit.page').then((m) => m.DepositPage),
  },
  {
    path: 'compte',
    title: 'Mon compte · la Plateforme',
    canActivate: [authGuard],
    loadComponent: () => import('./features/account/account.page').then((m) => m.AccountPage),
  },
  {
    path: 'recherche',
    title: 'Recherche · la Plateforme',
    loadComponent: comingSoon,
    data: {
      heading: 'La recherche arrive bientôt',
      text: 'Recherche par mots-clés, filtres par catégorie et recherche autour de chez vous sont en cours de développement.',
      icon: 'search',
    },
  },
  {
    path: 'favoris',
    title: 'Favoris · la Plateforme',
    canActivate: [authGuard],
    loadComponent: comingSoon,
    data: { heading: 'Vos favoris', text: 'Vous pourrez bientôt sauvegarder des annonces et être alerté des baisses de prix.', icon: 'heart' },
  },
  {
    path: 'messages',
    title: 'Messages · la Plateforme',
    canActivate: [authGuard],
    loadComponent: comingSoon,
    data: { heading: 'Votre messagerie', text: 'La messagerie en temps réel avec les acheteurs et vendeurs arrive bientôt.', icon: 'message' },
  },
  { path: '**', redirectTo: '' },
];
