import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';

import { StarterComponent } from './starter/starter.component';

import { DashboardComponent } from '@components/dashboard/dashboard.component';
import { HomeComponent } from '@components/crud/home/home.component';
import { NewComponent } from '@components/crud/new/new.component';

const routes: Routes = [
  { 
    path: 'starter', component: StarterComponent,
      children: [
        { path: 'dashboard', component: DashboardComponent },
        { path: 'crud', component: HomeComponent },
        { path: 'crud/new', component: NewComponent }
      ]
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class StarterRoutingModule { }
