import { Component, OnInit, OnDestroy } from '@angular/core';

@Component({
  selector: 'app-starter',
  templateUrl: './starter.component.html',
  styleUrls: ['./starter.component.css']
})
export class StarterComponent implements OnInit, OnDestroy {

  constructor() { }

  ngOnInit() {
    //window.dispatchEvent(new Event('resize'));
    document.body.className = 'hold-transition skin-blue sidebar-mini';
  }

  ngOnDestroy() {
    document.body.className = '';
  }

}
