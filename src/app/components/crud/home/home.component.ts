import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-home',
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit {
  public title: any;
  public breadcrumb: any;
  public breadcrumbActive: any;

  constructor() {
    this.title = "Crud";
    this.breadcrumb = [];
    this.breadcrumbActive = "Crud";
  }

  ngOnInit() {
    
  }

}
