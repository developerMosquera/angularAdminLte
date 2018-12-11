import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-new',
  templateUrl: './new.component.html',
  styleUrls: ['./new.component.css']
})
export class NewComponent implements OnInit {
  public title: any;
  public breadcrumb: any;
  public breadcrumbActive: any;

  public rowsDynamicForm: number;

  constructor() {
    this.title = "New";
    this.breadcrumb = [
      {
        "name": "Crud",
        "route": "crud"
      }
    ];
    this.breadcrumbActive = "New";
  }

  ngOnInit() {
    this.rowsDynamicForm = 1;
  }

  dynamicFormRemove() {
    this.rowsDynamicForm = this.rowsDynamicForm - 1;
  }

  dynamicFormAdd() {
    this.rowsDynamicForm = this.rowsDynamicForm + 1;

    console.log(this.rowsDynamicForm);
  }
  
}
