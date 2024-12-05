import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  title = 'Zadanie';
  imie:string="";
  Wybrany1:boolean[]=[];
  Wybrany2:boolean[]=[];
  Wybrany3:string="";
  ryby:any=["Szczupak","Łosoś","Pstrąg","Mały łosoś"];
  psy:any=[{id:1,value:"Kundel"},{id:2,value:"Szczeniak"},{id:3,value:"Buldog"},{id:4,value:"Pitbull"}];
  warzywa:any=["Pomidor","Szczypior","Cebula","Bananowy Ogórek"];
  wyswietlanie:boolean=false;
  wyswietl(){
    this.wyswietlanie=true;
  }
}
