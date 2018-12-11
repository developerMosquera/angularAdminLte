import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { StarterRoutingModule } from './starter-routing.module';
import { StarterComponent } from './starter/starter.component';
import { LayoutModule } from '../layout/layout.module';

import { DashboardComponent } from '@components/dashboard/dashboard.component';
import { BreadcrumbComponent } from '@components/breadcrumb/breadcrumb.component';
import { HomeComponent } from '@components/crud/home/home.component';
import { NewComponent } from '@components/crud/new/new.component';

import { DataTablesModule } from 'angular-datatables';

@NgModule({
  declarations: [
    StarterComponent,
    DashboardComponent,
    BreadcrumbComponent,
    HomeComponent,
    NewComponent
  ],
  imports: [
    CommonModule,
    StarterRoutingModule,
    LayoutModule,
    DataTablesModule
  ]
})
export class StarterModule { }
