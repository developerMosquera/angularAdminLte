import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { StarterRoutingModule } from './starter-routing.module';
import { StarterComponent } from './starter/starter.component';
import { LayoutModule } from '../layout/layout.module';

@NgModule({
  declarations: [StarterComponent],
  imports: [
    CommonModule,
    StarterRoutingModule,
    LayoutModule
  ]
})
export class StarterModule { }
